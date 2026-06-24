<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request, AuditService $auditService)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::query()->where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $auditService->log('login_failed', 'auth', 'LOGIN', null, [], ['username' => $credentials['username']], 'failed');
            throw ValidationException::withMessages(['username' => ['Username atau password salah.']]);
        }

        if (! $user->is_active) {
            return $this->error('Akun Anda telah dinonaktifkan. Hubungi administrator.', 403);
        }

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        $token = $user->createToken('api-token', $permissions, now()->addHours(8))->plainTextToken;
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $auditService->log('login_success', 'auth', 'LOGIN', $user);

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'user' => $this->userPayload($user),
        ], 'Login berhasil');
    }

    public function logout(Request $request, AuditService $auditService)
    {
        $auditService->log('logout', 'auth', 'LOGOUT', $request->user());
        $request->user()->currentAccessToken()?->delete();

        return $this->success(null, 'Logout berhasil.');
    }

    public function me(Request $request)
    {
        return $this->success($this->userPayload($request->user()));
    }

    public function changePassword(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['Password saat ini tidak sesuai.']]);
        }

        $request->user()->forceFill(['password' => Hash::make($data['new_password'])])->save();
        $auditService->log('password_changed', 'auth', 'CHANGE_PASSWORD', $request->user());

        return $this->success(null, 'Password berhasil diubah.');
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'customer_id' => $user->customer_id,
            'customer' => $user->customer ? [
                'id' => $user->customer->id,
                'name' => $user->customer->name,
            ] : null,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'theme_preference' => $user->theme_preference,
            'language_preference' => $user->language_preference,
            'notification_preferences' => $user->notification_preferences,
            'last_login_at' => $user->last_login_at,
        ];
    }
}
