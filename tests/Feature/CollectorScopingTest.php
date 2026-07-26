<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectorScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCollector(): User
    {
        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');

        return $collector;
    }

    private function createResidentAndUnit(string $unitId, string $residentName = 'Scoping Test Resident'): array
    {
        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => $residentName])
            ->assertCreated()
            ->json('data.resident.id');

        $this->postJson('/api/v1/units', [
            'id' => $unitId,
            'resident_id' => $residentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => substr($unitId, -2),
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        return ['resident_id' => $residentId, 'unit_id' => $unitId];
    }

    public function test_collector_cannot_read_or_write_units_outside_their_assignment(): void
    {
        $this->seed();

        $inScope = $this->createResidentAndUnit('ZZ981');
        $outOfScope = $this->createResidentAndUnit('ZZ982');

        $collector = $this->makeCollector();

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'unit',
            'unit_id' => $inScope['unit_id'],
        ])->assertCreated();

        Sanctum::actingAs($collector);

        // In-scope unit/resident: readable.
        $this->getJson("/api/v1/units/{$inScope['unit_id']}")->assertOk();
        $this->getJson("/api/v1/residents/{$inScope['resident_id']}")->assertOk();

        // Out-of-scope unit/resident: rejected.
        $this->getJson("/api/v1/units/{$outOfScope['unit_id']}")->assertForbidden();
        $this->getJson("/api/v1/residents/{$outOfScope['resident_id']}")->assertForbidden();

        // Out-of-scope write paths (visit, PTP) are rejected too, not just index/show.
        $this->postJson("/api/v1/units/{$outOfScope['unit_id']}/visits", [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'completed',
        ])->assertForbidden();

        $this->postJson("/api/v1/units/{$outOfScope['unit_id']}/payment-promises", [
            'promised_amount' => 100000,
            'promised_date' => now()->toDateString(),
            'status' => 'pending',
        ])->assertForbidden();

        // In-scope write paths succeed, and the collector_id is always server-set to
        // the acting collector, never trusting a client-supplied value.
        $otherCollector = $this->makeCollector();
        $visitId = $this->postJson("/api/v1/units/{$inScope['unit_id']}/visits", [
            'collector_id' => $otherCollector->id,
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'completed',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('collector_visits', [
            'id' => $visitId,
            'collector_id' => $collector->id,
        ]);
    }

    public function test_collector_index_listings_are_filtered_to_assigned_scope(): void
    {
        $this->seed();

        $inScope = $this->createResidentAndUnit('ZZ983');
        $this->createResidentAndUnit('ZZ984');

        $collector = $this->makeCollector();

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'unit',
            'unit_id' => $inScope['unit_id'],
        ])->assertCreated();

        Sanctum::actingAs($collector);

        $unitIds = collect($this->getJson('/api/v1/units')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($inScope['unit_id'], $unitIds);
        $this->assertNotContains('ZZ984', $unitIds);

        $residentIds = collect($this->getJson('/api/v1/residents')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($inScope['resident_id'], $residentIds);
    }

    public function test_duplicate_active_assignment_scope_is_rejected(): void
    {
        $this->seed();

        $unit = $this->createResidentAndUnit('ZZ985');
        $collector = $this->makeCollector();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'unit',
            'unit_id' => $unit['unit_id'],
        ])->assertCreated();

        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'unit',
            'unit_id' => $unit['unit_id'],
        ])->assertUnprocessable();
    }

    public function test_cluster_scope_assignment_covers_every_unit_in_the_cluster(): void
    {
        $this->seed();

        $unitA = $this->createResidentAndUnit('ZZ986');
        $unitB = $this->createResidentAndUnit('ZZ987');
        $collector = $this->makeCollector();

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'cluster',
            'cluster_id' => 'GA',
        ])->assertCreated();

        Sanctum::actingAs($collector);
        $this->getJson("/api/v1/units/{$unitA['unit_id']}")->assertOk();
        $this->getJson("/api/v1/units/{$unitB['unit_id']}")->assertOk();
    }
}
