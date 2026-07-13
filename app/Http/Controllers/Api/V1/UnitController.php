<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Unit::query()
            ->with(['cluster', 'propertyType', 'status', 'occupancy', 'resident'])
            ->search($request->query('search'))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->when($request->query('property_type_id'), fn ($q, $value) => $q->where('property_type_id', $value))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->where('resident_id', $value));

        return $this->paginated($query->orderBy('cluster_id')->orderBy('block')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateUnit($request);
        $data['created_by'] = $request->user()->id;
        $unit = Unit::query()->create($data);
        $auditService->log('unit_created', 'units', 'CREATE', $unit, [], $unit->toArray());
        $this->syncUnitOwnership($unit, $auditService);

        return $this->success($unit->load(['cluster', 'status', 'resident']), 'Unit berhasil dibuat.', 201);
    }

    /**
     * Jaga agar akun login customer (users.unit_id) selalu mencerminkan kepemilikan
     * Unit yang sebenarnya (units.resident_id) setiap kali Unit dibuat/pemiliknya diubah:
     *   1) Lepaskan akun customer mana pun yang unit_id-nya masih menunjuk ke Unit ini
     *      padahal penghuninya sudah bukan pemilik lagi (dipindah ke unit lain milik
     *      mereka sendiri jika masih ada, atau dikosongkan jika tidak ada).
     *   2) Tautkan/perbarui akun customer milik pemilik baru ke Unit ini, supaya penghuni
     *      langsung bisa melihat data unitnya di portal tanpa langkah manual tambahan.
     * Tanpa ini, halaman Admin (yang selalu query langsung dari units.resident_id) dan
     * halaman Customer (yang bergantung pada users.unit_id) bisa jadi tidak sinkron.
     */
    private function syncUnitOwnership(Unit $unit, AuditService $auditService): void
    {
        User::query()
            ->role('customer')
            ->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->whereNull('resident_id')->orWhere('resident_id', '!=', $unit->resident_id))
            ->get()
            ->each(function (User $stale) use ($auditService) {
                $old = $stale->toArray();
                $replacementUnitId = $stale->resident_id
                    ? Unit::query()->where('resident_id', $stale->resident_id)->value('id')
                    : null;
                $stale->forceFill(['unit_id' => $replacementUnitId])->save();
                $auditService->log('user_unlinked_from_unit', 'users', 'UPDATE', $stale, $old, $stale->toArray());
            });

        $owner = User::query()->role('customer')->where('resident_id', $unit->resident_id)->first();

        if ($owner && $owner->unit_id !== $unit->id) {
            $old = $owner->toArray();
            $owner->forceFill(['unit_id' => $unit->id])->save();
            $auditService->log('user_linked_to_unit', 'users', 'UPDATE', $owner, $old, $owner->toArray());
        }
    }

    public function show(Unit $unit)
    {
        return $this->success($unit->load(['cluster', 'propertyType', 'occupancy', 'status', 'resident', 'billings.status', 'users.roles']));
    }

    public function update(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $this->validateUnit($request, $unit);
        $data['updated_by'] = $request->user()->id;
        $old = $unit->toArray();
        $unit->update($data);
        $auditService->log('unit_updated', 'units', 'UPDATE', $unit, $old, $unit->toArray());
        $this->syncUnitOwnership($unit, $auditService);

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
        return $request->validate([
            'id' => ['required', 'string', 'size:5', Rule::unique('units', 'id')->ignore($unit?->id, 'id')],
            'resident_id' => ['required', 'exists:residents,id'],
            'cluster_id' => ['required', 'exists:clusters,id'],
            'block' => ['required', 'string', 'max:5'],
            'lot_number' => ['required', 'string', 'max:10'],
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
            'notes' => ['nullable', 'string'],
        ]);
    }
}
