<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_user_can_login_and_fetch_profile(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'root',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user' => ['permissions']]]);
    }

    public function test_user_can_login_with_email(): void
    {
        $this->seed();

        $root = User::where('username', 'root')->first();
        $root->forceFill(['email' => 'root@example.test'])->save();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'root@example.test',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_user_can_login_with_unique_phone_number(): void
    {
        $this->seed();

        $root = User::where('username', 'root')->first();
        $root->forceFill(['phone' => '081234567890'])->save();

        $this->postJson('/api/v1/auth/login', [
            'username' => '081234567890',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_login_with_ambiguous_phone_number_is_rejected(): void
    {
        $this->seed();

        User::where('username', 'root')->update(['phone' => '081234567890']);
        User::where('username', 'cs')->update(['phone' => '081234567890']);

        $this->postJson('/api/v1/auth/login', [
            'username' => '081234567890',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed();

        User::where('username', 'cs')->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'cs',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_audit_log_is_root_only(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'cs')->first());
        $this->getJson('/api/v1/audit-logs')->assertForbidden();

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->getJson('/api/v1/audit-logs')->assertOk();
    }
}
