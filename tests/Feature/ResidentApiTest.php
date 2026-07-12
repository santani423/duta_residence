<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_resident_auto_creates_customer_login_account(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/residents', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi.santoso@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'resident' => ['id', 'name'],
                'login_account' => ['user_id', 'username', 'temporary_password'],
            ]]);

        $residentId = $response->json('data.resident.id');
        $username = $response->json('data.login_account.username');

        $this->assertSame('customer.'.strtolower($residentId), $username);
        $this->assertSame('password', $response->json('data.login_account.temporary_password'));

        $user = User::where('username', $username)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->unit_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('customer'));
    }
}
