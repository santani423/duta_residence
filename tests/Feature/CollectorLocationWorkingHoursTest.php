<?php

namespace Tests\Feature;

use App\Models\CollectorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectorLocationWorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    private function makeCollector(?string $dutyStart = null, ?string $dutyEnd = null): User
    {
        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');
        CollectorProfile::query()->create([
            'user_id' => $collector->id,
            'collector_code' => 'COL-WH'.$collector->id,
            'employment_status' => 'tetap',
            'account_status' => 'active',
            'duty_start_time' => $dutyStart,
            'duty_end_time' => $dutyEnd,
        ]);

        return $collector->fresh();
    }

    public function test_location_ping_is_rejected_outside_the_collectors_own_duty_hours(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $now = now()->format('H:i:s');
        $outsideStart = now()->addHours(2)->format('H:i:s');
        $outsideEnd = now()->addHours(3)->format('H:i:s');

        $collector = $this->makeCollector($outsideStart, $outsideEnd);
        Sanctum::actingAs($collector);

        $this->postJson('/api/v1/collector-locations', ['latitude' => -6.2, 'longitude' => 106.8])
            ->assertUnprocessable();

        $this->assertDatabaseCount('collector_locations', 0);
    }

    public function test_location_ping_is_accepted_within_the_collectors_own_duty_hours(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $collector = $this->makeCollector('00:00', '23:59');
        Sanctum::actingAs($collector);

        $this->postJson('/api/v1/collector-locations', ['latitude' => -6.2, 'longitude' => 106.8])
            ->assertCreated();
    }

    public function test_location_ping_uses_the_system_wide_default_when_the_collector_has_no_override(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        config(['collector.default_working_hours' => ['start' => '00:00', 'end' => '23:59']]);
        $collector = $this->makeCollector(null, null);
        Sanctum::actingAs($collector);

        $this->postJson('/api/v1/collector-locations', ['latitude' => -6.2, 'longitude' => 106.8])
            ->assertCreated();
    }
}
