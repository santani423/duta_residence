<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Carbon;
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

        // The login screen has always advertised "username, email, atau telepon" as
        // acceptable identifiers (see android login_screen.dart); the backend only ever
        // matched `username`/`email` until now. Email is safe to match directly because
        // it's already validated unique across the whole `users` table
        // (UserController/CollectorController). `phone` has no unique constraint, so it
        // only resolves a login when exactly one account has that number — otherwise we
        // fall through to the "salah" error rather than risk logging into the wrong account.
        $user = User::query()
            ->where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        if (! $user) {
            $phoneMatches = User::query()->where('phone', $credentials['username'])->limit(2)->get();
            if ($phoneMatches->count() === 1) {
                $user = $phoneMatches->first();
            }
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $auditService->log('login_failed', 'auth', 'LOGIN', null, [], ['username' => $credentials['username']], 'failed');
            throw ValidationException::withMessages(['username' => ['Username atau password salah.']]);
        }

        if (! $user->is_active) {
            return $this->error('Akun Anda telah dinonaktifkan. Hubungi administrator.', 403);
        }

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        $newToken = $user->createToken('api-token', $permissions, now()->addHours(8));
        $token = $newToken->plainTextToken;
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip(), 'active_token_id' => $newToken->accessToken->id])->save();

        $auditService->log('login_success', 'auth', 'LOGIN', $user);

        return $this->success([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'user' => $this->userPayload($user),
        ], 'Login berhasil');
    }

    /**
     * Always responds with the same neutral message regardless of whether the
     * email is registered - the caller must not be able to enumerate accounts
     * from this endpoint.
     */
    public function forgotPassword(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Only reveal whether the email is registered while debugging locally; in
        // production this stays silent so the endpoint can't be used to enumerate accounts.
        if (config('app.debug') && ! User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak terdaftar.',
            ]);
        }

        $status = Password::sendResetLink($data);

        $auditService->log('password_reset_requested', 'auth', 'FORGOT_PASSWORD', null, [], ['email' => $data['email']], $status === Password::RESET_LINK_SENT ? 'success' : 'failed');

        return $this->success(null, 'Jika alamat email terdaftar, instruksi untuk mengatur ulang kata sandi akan dikirim.');
    }

    /**
     * Lets the frontend show "tautan tidak valid" before rendering the form,
     * without consuming the token (Password::reset() below does that).
     */
    public function validateResetToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
        ]);

        $record = DB::table(config('auth.passwords.users.table'))->where('email', $data['email'])->first();
        $expireMinutes = config('auth.passwords.users.expire', 60);

        $valid = $record
            && Hash::check($data['token'], $record->token)
            && ! Carbon::parse($record->created_at)->addMinutes($expireMinutes)->isPast();

        return $this->success(['valid' => (bool) $valid], $valid ? 'Token valid.' : 'Tautan reset tidak valid atau sudah kedaluwarsa.');
    }

    public function resetPassword(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Tautan reset tidak valid, sudah kedaluwarsa, atau sudah digunakan. Silakan minta tautan baru.'],
            ]);
        }

        $user = User::where('email', $data['email'])->first();
        $auditService->log('password_reset_completed', 'auth', 'RESET_PASSWORD', $user);

        return $this->success(null, 'Password berhasil direset. Silakan masuk dengan password baru Anda.');
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
            'unit_id' => $user->unit_id,
            'unit' => $user->unit ? [
                'id' => $user->unit->id,
                'name' => $user->unit->resident?->name,
            ] : null,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'theme_preference' => $user->theme_preference,
            'language_preference' => $user->language_preference,
            'notification_preferences' => $user->notification_preferences,
            'last_login_at' => $user->last_login_at,
            'collector_profile' => $user->hasRole('collector') ? $this->collectorProfilePayload($user) : null,
        ];
    }

    /**
     * Reuses the app's existing auth/me bootstrap call (session_controller.dart already
     * fetches this on every cold start) instead of adding a dedicated endpoint/network
     * call to the Android app just to show the collector's own code/photo.
     */
    private function collectorProfilePayload(User $user): ?array
    {
        $profile = $user->collectorProfile()->with('photos')->first();
        if (! $profile) {
            return null;
        }

        return [
            'collector_code' => $profile->collector_code,
            'whatsapp_number' => $profile->whatsapp_number,
            'employment_status' => $profile->employment_status,
            'account_status' => $profile->account_status,
            'photo_path' => $profile->photos->first()?->path,
        ];
    }
}
