<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Resident;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Resident::query()
            ->search($request->query('search'));

        return $this->paginated($query->orderBy('name')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateResident($request);
        $data['id'] = $this->generateResidentId();
        $data['created_by'] = $request->user()->id;
        $resident = Resident::query()->create($data);
        $auditService->log('resident_created', 'residents', 'CREATE', $resident, [], $resident->toArray());

        return $this->success($resident, 'Penghuni berhasil dibuat.', 201);
    }

    public function show(Resident $resident)
    {
        return $this->success($resident->load([
            'district.regency',
            'units.cluster',
            'units.status',
            'units.propertyType',
            'units.occupancy',
            'units.users',
            'photos',
        ]));
    }

    public function update(Request $request, Resident $resident, AuditService $auditService)
    {
        $data = $this->validateResident($request, $resident);
        $data['updated_by'] = $request->user()->id;
        $old = $resident->toArray();
        $resident->update($data);
        $auditService->log('resident_updated', 'residents', 'UPDATE', $resident, $old, $resident->toArray());

        return $this->success($resident->refresh(), 'Penghuni berhasil diperbarui.');
    }

    public function destroy(Resident $resident, AuditService $auditService)
    {
        if ($resident->units()->exists()) {
            return $this->error('Penghuni tidak dapat dihapus karena masih memiliki unit.', 422);
        }

        $old = $resident->toArray();
        $resident->delete();
        $auditService->log('resident_deleted', 'residents', 'DELETE', $resident, $old, []);

        return $this->success(null, 'Penghuni berhasil dihapus.');
    }

    private function generateResidentId(): string
    {
        return DB::transaction(function () {
            $last = Resident::query()
                ->where('id', 'like', 'RS%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('id');

            $next = $last ? ((int) substr($last, 2)) + 1 : 1;

            return 'RS'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    private function validateResident(Request $request, ?Resident $resident = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'email' => ['nullable', 'email', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:30'],
            'identity_type' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
