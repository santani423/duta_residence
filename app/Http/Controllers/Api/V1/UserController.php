<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
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
            ->with('roles')
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
        $data = $this->validateUser($request);
        $user = User::query()->create([...$data, 'password' => Hash::make($data['password'])]);
        $user->assignRole($data['role']);
        $auditService->log('user_created', 'users', 'CREATE', $user, [], $user->toArray());

        return $this->success($user->load('roles'), 'User berhasil dibuat.', 201);
    }

    public function show(User $user)
    {
        return $this->success([
            'user' => $user->load('roles'),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'activity_logs' => $user->auditLogs()->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditService $auditService)
    {
        $data = $this->validateUser($request, $user);
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

    private function validateUser(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(Role::query()->pluck('name')->all())],
            'is_active' => ['sometimes', 'boolean'],
            'theme_preference' => ['sometimes', Rule::in(['light', 'dark', 'system'])],
        ]);
    }
}
