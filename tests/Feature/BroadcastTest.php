<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_broadcast_is_delivered_in_app_without_a_whatsapp_provider(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/broadcasts', [
            'type' => 'announcement',
            'message' => 'Pengumuman internal untuk seluruh kolektor.',
        ])->assertCreated();

        $this->assertSame('announcement', $response->json('data.type'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.success_count'));
    }

    public function test_collector_whatsapp_broadcast_fails_gracefully_without_a_configured_provider_token(): void
    {
        $this->seed();
        config(['services.fonnte.token' => null]);

        $collector = User::factory()->create(['is_active' => true, 'phone' => '081234567890']);
        $collector->assignRole('collector');

        $root = User::where('username', 'root')->first();
        Sanctum::actingAs($root);
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'cluster',
            'cluster_id' => 'GA',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/broadcasts', [
            'type' => 'collector',
            'message' => 'Segera selesaikan target hari ini.',
            'collector_ids' => [$collector->id],
        ])->assertCreated();

        $this->assertSame(0, $response->json('data.success_count'));
        $this->assertSame(1, $response->json('data.fail_count'));
        $this->assertDatabaseHas('broadcast_recipients', [
            'recipient_id' => (string) $collector->id,
            'delivery_status' => 'failed',
            'provider_response' => 'provider_not_configured',
        ]);
    }
}
