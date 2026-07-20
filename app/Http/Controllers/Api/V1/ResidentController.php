<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Resident;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ResidentAccountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Resident::query()
            ->search($request->query('search'))
            ->address($request->query('address'))
            ->cluster($request->query('cluster_id'))
            ->block($request->query('block'));

        return $this->paginated($query->orderBy('name')->paginate($request->integer('per_page', 15)));
    }

    /**
     * Cek ketersediaan email/phone/username secara langsung dari form (dipanggil saat blur).
     */
    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['email', 'phone', 'username'])],
            'value' => ['required', 'string'],
            'exclude_id' => ['nullable', 'string'],
        ]);

        if ($data['field'] === 'username') {
            return $this->success([
                'taken' => User::query()->where('username', $data['value'])->exists(),
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

    public function store(Request $request, AuditService $auditService, ResidentAccountService $accounts)
    {
        $data = $this->validateResident($request);
        $username = $data['username'] ?? null;
        unset($data['username']);
        $data['id'] = $accounts->generateResidentId();
        $data['created_by'] = $request->user()->id;
        $resident = Resident::query()->create($data);
        $auditService->log('resident_created', 'residents', 'CREATE', $resident, [], $resident->toArray());

        $loginAccount = $accounts->createCustomerAccount($resident, $auditService, $username);

        return $this->success([
            'resident' => $resident,
            'login_account' => $loginAccount,
        ], 'Penghuni berhasil dibuat.', 201);
    }

    public function show(Resident $resident)
    {
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
                'nullable', 'string', 'max:20',
                'regex:/^(\+62|62|0)8[1-9][0-9]{6,10}$/',
                Rule::unique('residents', 'phone')->ignore($resident?->id),
            ],
            'telephone' => ['nullable', 'string', 'max:20'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'email' => [
                'nullable', 'email', 'max:100',
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
        ], [
            'phone.regex' => 'Nomor HP tidak valid.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, - dan _.',
        ]);
    }
}
