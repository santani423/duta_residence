<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PaymentGatewaySetting;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\ResidentAccountService;
use App\Services\UnitOwnershipSyncService;
use App\Services\UnitVaNumberService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CollectorAssignmentService $assignmentService)
    {
        $query = Resident::query()
            ->search($request->query('search'))
            ->address($request->query('address'))
            ->cluster($request->query('cluster_id'))
            ->block($request->query('block'))
            ->unitStatus($request->query('unit_status'))
            ->withCount(['units', 'tenantUnits']);

        if ($request->user()->hasRole('collector')) {
            $query->whereIn('id', $assignmentService->residentIdsFor($request->user()));
        }

        $residents = $query->orderBy('name')->paginate($request->integer('per_page', 15));
        $residents->getCollection()->each->append('unit_status');

        return $this->paginated($residents);
    }

    /**
     * Cek ketersediaan email/phone/username secara langsung dari form (dipanggil saat blur).
     */
    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['email', 'phone', 'username', 'va_suffix'])],
            'value' => ['required', 'string'],
            'exclude_id' => ['nullable', 'string'],
        ]);

        if ($data['field'] === 'username') {
            return $this->success([
                'taken' => User::query()->where('username', $data['value'])->exists(),
            ]);
        }

        if ($data['field'] === 'va_suffix') {
            $vaNumber = PaymentGatewaySetting::current()->vaPrefix().$data['value'];

            return $this->success([
                'taken' => app(UnitVaNumberService::class)->isTaken($vaNumber, $data['exclude_id'] ?? null),
            ]);
        }

        $owner = Resident::query()
            ->where($data['field'], $data['value'])
            ->when($data['exclude_id'] ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
            ->first();

        return $this->success([
            'taken' => (bool) $owner,
            'owner_name' => $owner?->name,
        ]);
    }

    public function store(Request $request, AuditService $auditService, ResidentAccountService $accounts, UnitOwnershipSyncService $ownershipSync, UnitVaNumberService $vaNumbers)
    {
        $data = $this->validateResident($request);
        $unitId = $data['unit_id'] ?? null;
        $vaSuffix = $data['va_suffix'] ?? null;
        unset($data['unit_id'], $data['va_suffix']);

        // Dicek sebelum penghuni dibuat agar VA yang tidak valid/duplikat tidak meninggalkan penghuni tanpa unit.
        $vaNumber = $unitId ? $vaNumbers->compose($vaSuffix, Unit::query()->find($unitId)) : null;
        $username = $data['username'] ?? null;
        unset($data['username']);
        $data['id'] = $accounts->generateResidentId();
        $data['created_by'] = $request->user()->id;
        $resident = Resident::query()->create($data);
        $auditService->log('resident_created', 'residents', 'CREATE', $resident, [], $resident->toArray());

        $loginAccount = $accounts->createCustomerAccount($resident, $auditService, $username);

        if ($unitId) {
            // Unit yang dipilih sudah divalidasi masih kosong (resident_id null) saat validasi
            // request, tapi dikunci ulang di sini untuk menutup celah race condition dengan
            // request lain yang menautkan unit yang sama secara bersamaan.
            $unit = Unit::query()->whereNull('resident_id')->findOrFail($unitId);
            $oldUnit = $unit->toArray();
            $unit->update(['resident_id' => $resident->id, 'va_number' => $vaNumber, 'updated_by' => $request->user()->id] + $unit->activationAttributes());
            $auditService->log('unit_updated', 'units', 'UPDATE', $unit, $oldUnit, $unit->toArray());
            $ownershipSync->sync($unit, $auditService);
        }

        return $this->success([
            'resident' => $resident,
            'login_account' => $loginAccount,
        ], 'Penghuni berhasil dibuat.', 201);
    }

    public function show(Request $request, Resident $resident, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            abort_unless(in_array($resident->id, $assignmentService->residentIdsFor($request->user()), true), 403, 'Penghuni ini tidak ditugaskan kepada Anda.');
        }

        $resident->append('unit_status');

        return $this->success($resident->load([
            'district.regency',
            'units.cluster',
            'units.status',
            'units.propertyType',
            'units.occupancy',
            'units.tenantResident',
            'units.users',
            'users.roles',
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

    /**
     * Aktifkan / nonaktifkan penghuni. Akun login penghuni ikut disinkronkan agar
     * penghuni nonaktif tidak bisa masuk ke portal customer.
     */
    public function setActive(Request $request, Resident $resident, AuditService $auditService)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $old = $resident->toArray();

        $resident->update(['is_active' => $data['is_active'], 'updated_by' => $request->user()->id]);
        $resident->users()->update(['is_active' => $data['is_active']]);
        $auditService->log('resident_updated', 'residents', 'UPDATE', $resident, $old, $resident->toArray());

        return $this->success(
            $resident->refresh(),
            $data['is_active'] ? 'Penghuni berhasil diaktifkan.' : 'Penghuni berhasil dinonaktifkan.',
        );
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

    private function validateResident(Request $request, ?Resident $resident = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => [
                $resident ? 'sometimes' : 'required_with:unit_id', 'nullable', 'string', 'max:20',
                'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/',
                Rule::unique('residents', 'phone')->ignore($resident?->id),
            ],
            'telephone' => ['nullable', 'string', 'max:20'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'email' => [
                $resident ? 'sometimes' : 'required_with:unit_id', 'nullable', 'email', 'max:100',
                Rule::unique('residents', 'email')->ignore($resident?->id),
            ],
            'identity_number' => ['nullable', 'string', 'max:30'],
            'identity_type' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'username' => [
                'nullable', 'string', 'min:4', 'max:50',
                'regex:/^[a-zA-Z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            // Nomor VA melekat di unit, jadi wajib diisi (9 digit) begitu penghuni ditautkan ke unit.
            'va_suffix' => $resident ? ['sometimes'] : ['required_with:unit_id', 'nullable', 'string'],
            'unit_id' => $resident ? ['sometimes'] : [
                'nullable',
                Rule::exists('units', 'id')->whereNull('resident_id'),
            ],
        ], [
            'phone.regex' => 'Nomor HP tidak valid.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, - dan _.',
            'phone.required_with' => 'Nomor HP wajib diisi saat penghuni ditautkan ke unit.',
            'email.required_with' => 'Email wajib diisi saat penghuni ditautkan ke unit.',
            'va_suffix.required_with' => 'Nomor virtual account wajib diisi saat penghuni ditautkan ke unit.',
            'unit_id.exists' => 'Unit tidak ditemukan atau sudah memiliki penghuni.',
        ]);
    }
}
