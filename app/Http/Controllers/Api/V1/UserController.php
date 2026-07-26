<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = User::query()
            ->with(['roles', 'unit.resident', 'resident'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$value}%")
                ->orWhere('username', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%")
                ->orWhere('phone', 'like', "%{$value}%")))
            ->when($request->query('role'), fn ($q, $value) => $q->role($value))
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->syncResidentIdFromUnit($this->validateUser($request));
        $user = User::query()->create([...$data, 'password' => Hash::make($data['password'])]);
        $user->assignRole($data['role']);
        $auditService->log('user_created', 'users', 'CREATE', $user, [], $user->toArray());

        return $this->success($user->load('roles'), 'User berhasil dibuat.', 201);
    }

    public function show(User $user)
    {
        return $this->success([
            'user' => $user->load(['roles', 'unit.resident', 'resident']),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'activity_logs' => $user->auditLogs()->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditService $auditService)
    {
        $data = $this->syncResidentIdFromUnit($this->validateUser($request, $user));
        $old = $user->toArray();

        unset($data['password']);
        $user->update($data);
        $user->syncRoles([$data['role']]);
        $auditService->log('user_updated', 'users', 'UPDATE', $user, $old, $user->toArray());

        return $this->success($user->refresh()->load('roles'), 'User berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return $this->success(null, 'User berhasil dihapus.');
    }

    public function resetPassword(User $user, AuditService $auditService)
    {
        $temporaryPassword = 'password';
        $user->forceFill(['password' => Hash::make($temporaryPassword)])->save();
        $auditService->log('user_password_reset', 'users', 'RESET_PASSWORD', $user);

        return $this->success(['temporary_password' => $temporaryPassword], 'Password berhasil direset.');
    }

    public function toggleStatus(User $user, AuditService $auditService)
    {
        $user->forceFill(['is_active' => ! $user->is_active])->save();
        $auditService->log('user_status_toggled', 'users', 'TOGGLE_STATUS', $user);

        return $this->success($user, 'Status user berhasil diperbarui.');
    }

    public function activities(User $user)
    {
        return $this->paginated($user->auditLogs()->latest()->paginate(request()->integer('per_page', 15)));
    }

    /**
     * Saat admin menautkan unit_id lewat User Management, samakan juga resident_id-nya
     * dengan pemilik Unit tsb, supaya identitas penghuni pada akun ini tetap konsisten
     * dengan relasi yang dipakai halaman Customer (lihat ResidentPortalController).
     */
    private function syncResidentIdFromUnit(array $data): array
    {
        if (! empty($data['unit_id'])) {
            $data['resident_id'] = Unit::query()->find($data['unit_id'])?->resident_id;
        }

        return $data;
    }

    /**
     * `collector` is deliberately excluded from the assignable roles here. Collector
     * accounts always need a matching `collector_profiles` row (code, employment/account
     * status, assignments, ...), which only CollectorController's store()/update() create
     * atomically alongside the User. Allowing `collector` through this generic form would
     * let an admin create/promote a User with the role but no profile - an orphaned
     * "collector" that can log in but has no profile, no assignments, and breaks every
     * `collector_profile`-dependent screen. Collector accounts are managed exclusively
     * through the "Manajemen Kolektor" module.
     */
    private function assignableRoles(?User $user = null): array
    {
        $roles = Role::query()->pluck('name')->reject(fn ($name) => in_array($name, ['collector', 'supervisor'], true));

        // An existing collector/supervisor account edited here (e.g. to fix a typo in the
        // name) must be allowed to keep its current role, just not to newly acquire it -
        // same reasoning as the collector exclusion above: supervisor accounts always need
        // a matching `supervisor_profiles` row, which only SupervisorController creates
        // atomically alongside the User.
        foreach (['collector', 'supervisor'] as $exclusiveRole) {
            if ($user?->hasRole($exclusiveRole)) {
                $roles->push($exclusiveRole);
            }
        }

        return $roles->values()->all();
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => [
                'required',
                Rule::in($this->assignableRoles($user)),
            ],
            'unit_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'customer'),
                'nullable', 'string', Rule::exists('units', 'id'),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'theme_preference' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
        ]);
    }
}
