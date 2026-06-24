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
