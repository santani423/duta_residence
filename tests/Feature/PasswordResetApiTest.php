<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_notification_for_registered_email(): void
    {
        Notification::fake();
        $this->seed();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'root@grandduta.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika alamat email terdaftar, instruksi untuk mengatur ulang kata sandi akan dikirim.');

        Notification::assertSentTo(User::where('email', 'root@grandduta.test')->first(), ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_same_neutral_message_for_unregistered_email(): void
    {
        Notification::fake();
        $this->seed();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@nowhere.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika alamat email terdaftar, instruksi untuk mengatur ulang kata sandi akan dikirim.');

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token_and_login_with_new_password(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password/validate', ['email' => $user->email, 'token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertOk()->assertJsonPath('success', true);

        // Old password no longer works.
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'password'])
            ->assertUnprocessable();

        // New password works.
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => 'brand-new-password-123'])
            ->assertOk()->assertJsonPath('success', true);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now()->subMinutes(120),
        ]);

        $this->postJson('/api/v1/auth/reset-password/validate', ['email' => $user->email, 'token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', false);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertUnprocessable();
    }

    public function test_already_used_token_is_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $payload = [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ];

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();

        // Reusing the same token a second time must fail even with a different new password.
        $this->postJson('/api/v1/auth/reset-password', [
            ...$payload,
            'password' => 'another-password-456',
            'password_confirmation' => 'another-password-456',
        ])->assertUnprocessable();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'not-the-real-token',
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertUnprocessable();
    }

    public function test_password_shorter_than_policy_is_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'does-not-match-456',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $this->seed();
        $user = User::where('email', 'root@grandduta.test')->first();
        $user->createToken('api-token');
        $this->assertSame(1, $user->tokens()->count());

        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'brand-new-password-123',
            'password_confirmation' => 'brand-new-password-123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
