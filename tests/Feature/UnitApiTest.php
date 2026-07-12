<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_unit_auto_links_residents_pending_customer_account(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Auto Link Resident'])
            ->assertCreated()
            ->json('data.resident.id');

        $user = User::where('resident_id', $residentId)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->unit_id);

        $this->postJson('/api/v1/units', [
            'id' => 'ZZ999',
            'resident_id' => $residentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '99',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        $this->assertSame('ZZ999', $user->refresh()->unit_id);
    }

    public function test_resident_portal_lists_all_units_owned_by_the_resident(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Multi Unit Resident'])
            ->assertCreated()
            ->json('data.resident.id');

        $customerUsername = User::where('resident_id', $residentId)->first()->username;

        foreach (['ZZ997', 'ZZ998'] as $unitId) {
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
        }

        Sanctum::actingAs(User::where('username', $customerUsername)->first());

        $response = $this->getJson('/api/v1/resident/property')->assertOk();
        $unitIds = collect($response->json('data'))->pluck('unit_id')->all();

        $this->assertEqualsCanonicalizing(['ZZ997', 'ZZ998'], $unitIds);
    }
}
