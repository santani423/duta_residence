<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\PenaltyService;
use App\Services\UnitCodeGeneratorService;
use App\Services\UnitOwnershipSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CollectorAssignmentService $assignmentService)
    {
        $query = Unit::query()
            ->with(['cluster', 'propertyType', 'status', 'occupancy', 'resident'])
            ->search($request->query('search'))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->when($request->query('property_type_id'), fn ($q, $value) => $q->where('property_type_id', $value))
            ->occupancyStatus($request->query('occupancy_status'))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->where('resident_id', $value))
            ->when($request->query('block'), fn ($q, $value) => $q->where('block', 'like', "%{$value}%"))
            ->when($request->query('lot_number'), fn ($q, $value) => $q->where('lot_number', 'like', "%{$value}%"))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('resident_id'))
            ->when($request->query('customer'), fn ($q, $value) => $q->whereHas('resident', fn ($r) => $r->where('name', 'like', "%{$value}%")))
            ->when($request->query('address'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('block', 'like', "%{$value}%")
                ->orWhere('lot_number', 'like', "%{$value}%")));

        if ($request->user()->hasRole('collector')) {
            $query->whereIn('id', $assignmentService->unitIdsFor($request->user()));
        }

        return $this->paginated($query->orderBy('cluster_id')->orderBy('block')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService, UnitOwnershipSyncService $ownershipSync, UnitCodeGeneratorService $codeGenerator)
    {
        $data = $this->validateUnit($request);
        $data['created_by'] = $request->user()->id;

        if (($data['status_id'] ?? null) === 'AK' && empty($data['handover_date'])) {
            $data['handover_date'] = now()->toDateString();
        }

        // Menghasilkan kode unit lalu menyimpannya dikunci per cluster (lihat
        // UnitCodeGeneratorService), tapi tetap dibungkus retry di sini sebagai jaring
        // pengaman kedua: kalau dua request tetap berhasil menghitung nomor urut yang
        // sama (mis. driver DB yang tidak mendukung row lock), unique constraint pada
        // primary key units.id akan menolak insert-nya dan kita coba lagi dengan nomor
        // berikutnya alih-alih gagal total.
        $attempts = 0;

        do {
            $attempts++;

            try {
                $unit = DB::transaction(function () use ($data, $codeGenerator) {
                    $data['id'] = $codeGenerator->generate($data['cluster_id']);

                    return Unit::query()->create($data);
                });
                break;
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                // $e->getMessage() also has the full SQL (with every column, "lot_number"
                // included) appended by Laravel for debugging, so it can't be used to tell
                // which constraint failed. The raw driver message in errorInfo[2] only
                // names the columns/index actually violated: SQLite says "UNIQUE constraint
                // failed: units.cluster_id, units.block, units.lot_number" and MySQL's key
                // name is "units_cluster_id_block_lot_number_unique" - both mention
                // "lot_number" only for that composite constraint, never for a plain
                // primary-key (units.id / PRIMARY) collision.
                $driverMessage = $e->errorInfo[2] ?? '';

                if (str_contains($driverMessage, 'lot_number')) {
                    $this->assertLotNumberAvailable($data['cluster_id'], $data['block'], $data['lot_number'], null);

                    throw $e;
                }

                if ($attempts >= 5) {
                    throw $e;
                }
            }
        } while (true);

        $auditService->log('unit_created', 'units', 'CREATE', $unit, [], $unit->toArray());
        $ownershipSync->sync($unit, $auditService);

        return $this->success($unit->load(['cluster', 'status', 'resident']), 'Unit berhasil dibuat.', 201);
    }

    public function show(Request $request, Unit $unit, PenaltyService $penaltyService, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $unit->id);
        }

        $unit->load(['cluster', 'propertyType', 'occupancy', 'status', 'resident', 'discountRule', 'billings.status', 'users.roles']);
        $unit->setRelation('billings', $unit->billings->map(fn (Billing $billing) => [
            ...$billing->toArray(),
            'penalty_detail' => $penaltyService->calculateInvoiceTotal($billing->setRelation('unit', $unit)),
        ]));

        return $this->success($unit);
    }

    public function update(Request $request, Unit $unit, AuditService $auditService, UnitOwnershipSyncService $ownershipSync)
    {
        $data = $this->validateUnit($request, $unit);
        $data['updated_by'] = $request->user()->id;

        $activatingUnit = $unit->status_id !== 'AK' && ($data['status_id'] ?? $unit->status_id) === 'AK';

        if ($activatingUnit && empty($data['va_number'] ?? $unit->va_number)) {
            throw ValidationException::withMessages([
                'va_number' => ['Nomor virtual account wajib diisi untuk mengaktifkan unit melalui serah terima kunci.'],
            ]);
        }

        if (($data['status_id'] ?? $unit->status_id) === 'AK' && empty($data['handover_date']) && empty($unit->handover_date)) {
            $data['handover_date'] = now()->toDateString();
        }

        $old = $unit->toArray();
        $unit->update($data);
        $auditService->log('unit_updated', 'units', 'UPDATE', $unit, $old, $unit->toArray());
        $ownershipSync->sync($unit, $auditService);

        return $this->success($unit->refresh()->load(['cluster', 'status', 'resident']), 'Unit berhasil diperbarui.');
    }

    public function destroy(Unit $unit, AuditService $auditService)
    {
        $old = $unit->toArray();
        $unit->delete();
        $auditService->log('unit_deleted', 'units', 'DELETE', $unit, $old, []);

        return $this->success(null, 'Unit berhasil dihapus.');
    }

    public function convertProperty(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $request->validate([
            'property_type_id' => ['required', Rule::in(['B'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        if ($unit->property_type_id !== 'K') {
            return $this->error('Hanya kavling developer yang dapat dikonversi menjadi bangunan.', 422);
        }

        $old = $unit->toArray();
        $unit->update([
            'property_type_id' => $data['property_type_id'],
            'handover_date' => now()->toDateString(),
            'notes' => $data['notes'] ?? $unit->notes,
            'updated_by' => $request->user()->id,
        ]);
        $auditService->log('unit_property_converted', 'units', 'CONVERT_PROPERTY', $unit, $old, $unit->toArray());

        return $this->success($unit->refresh(), 'Properti berhasil dikonversi.');
    }

    private function validateUnit(Request $request, ?Unit $unit = null): array
    {
        $data = $request->validate([
            'va_number' => ['nullable', 'string', 'max:32', Rule::unique('units', 'va_number')->ignore($unit?->id)],
            'resident_id' => ['nullable', 'exists:residents,id'],
            'cluster_id' => ['required', 'exists:clusters,id'],
            'block' => ['required', 'string', 'max:5'],
            'lot_number' => ['required', 'regex:/^[0-9]{1,10}$/'],
            'property_type_id' => ['required', 'exists:property_types,id'],
            'building_area' => ['nullable', 'numeric', 'min:0'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'handover_date' => ['nullable', 'date'],
            'occupancy_id' => ['required', 'exists:occupancy_statuses,id'],
            'status_id' => ['required', 'exists:resident_statuses,id'],
            'occupancy_role' => ['sometimes', Rule::in(['pemilik', 'penyewa', 'keluarga', 'sementara'])],
            'tenancy_start_date' => ['nullable', 'date'],
            'tenancy_end_date' => ['nullable', 'date'],
            'is_penalty_eligible' => ['sometimes', 'boolean'],
            'is_discount_eligible' => ['sometimes', 'boolean'],
            'discount_rule_id' => ['nullable', 'exists:discount_rules,id'],
            'notes' => ['nullable', 'string'],
        ], [
            'va_number.unique' => 'Nomor virtual account ini sudah digunakan oleh unit lain. Silakan gunakan nomor lain.',
            'lot_number.regex' => 'Nomor unit harus berupa angka, contoh: 1 (bukan 01 atau 001).',
        ]);

        $this->assertLotNumberAvailable($data['cluster_id'], $data['block'], $data['lot_number'], $unit);

        return $data;
    }

    private function assertLotNumberAvailable(string $clusterId, string $block, string $lotNumber, ?Unit $unit): void
    {
        $duplicate = Unit::query()
            ->where('cluster_id', $clusterId)
            ->where('block', $block)
            ->where('lot_number', $lotNumber)
            ->when($unit, fn ($query) => $query->where('id', '!=', $unit->id))
            ->exists();

        if (! $duplicate) {
            return;
        }

        $clusterName = Cluster::query()->whereKey($clusterId)->value('name') ?? $clusterId;

        throw ValidationException::withMessages([
            'lot_number' => ["Unit dengan Blok {$block} Nomor {$lotNumber} sudah terdaftar di Cluster {$clusterName}. Silakan gunakan Blok atau Nomor Unit lain."],
        ]);
    }
}
