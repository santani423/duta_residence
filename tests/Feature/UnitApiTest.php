<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Services\UnitCodeGeneratorService;
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

        $unitId = $this->postJson('/api/v1/units', [
            'resident_id' => $residentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '99',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $this->assertSame($unitId, $user->refresh()->unit_id);
    }

    public function test_resident_portal_lists_all_units_owned_by_the_resident(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Multi Unit Resident'])
            ->assertCreated()
            ->json('data.resident.id');

        $customerUsername = User::where('resident_id', $residentId)->first()->username;

        $createdUnitIds = [];
        foreach (['97', '98'] as $lotNumber) {
            $createdUnitIds[] = $this->postJson('/api/v1/units', [
                'resident_id' => $residentId,
                'cluster_id' => 'GA',
                'block' => 'Z',
                'lot_number' => $lotNumber,
                'property_type_id' => 'B',
                'occupancy_id' => '1',
                'status_id' => 'AK',
            ])->assertCreated()->json('data.id');
        }

        Sanctum::actingAs(User::where('username', $customerUsername)->first());

        $response = $this->getJson('/api/v1/resident/property')->assertOk();
        $unitIds = collect($response->json('data'))->pluck('unit_id')->all();

        $this->assertEqualsCanonicalizing($createdUnitIds, $unitIds);
    }

    public function test_reassigning_unit_ownership_relinks_new_owner_and_releases_old_owner(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $oldResidentId = $this->postJson('/api/v1/residents', ['name' => 'Old Owner'])->json('data.resident.id');
        $newResidentId = $this->postJson('/api/v1/residents', ['name' => 'New Owner'])->json('data.resident.id');

        $unitId = $this->postJson('/api/v1/units', [
            'resident_id' => $oldResidentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '96',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $oldUser = User::where('resident_id', $oldResidentId)->first();
        $newUser = User::where('resident_id', $newResidentId)->first();
        $this->assertSame($unitId, $oldUser->unit_id);
        $this->assertNull($newUser->unit_id);

        // Transfer ownership of the same unit to the new resident via the admin edit flow.
        $this->putJson("/api/v1/units/{$unitId}", [
            'resident_id' => $newResidentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '96',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertOk();

        $this->assertNull($oldUser->refresh()->unit_id, 'old owner should be released once the unit no longer belongs to them');
        $this->assertSame($unitId, $newUser->refresh()->unit_id, 'new owner should be linked to the reassigned unit');

        Sanctum::actingAs($oldUser);
        $this->getJson('/api/v1/resident/dashboard')->assertForbidden();

        Sanctum::actingAs($newUser);
        $this->getJson('/api/v1/resident/dashboard')->assertOk()->assertJsonPath('data.property.unit_id', $unitId);
    }

    public function test_unit_code_is_generated_automatically_and_sequentially(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        // UnitSeeder already seeds DA001..DA003 for the DA cluster, so the next two
        // units created for it must continue the sequence without gaps or manual input.
        $firstId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '04',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $secondId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '05',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $this->assertSame('DA004', $firstId);
        $this->assertSame('DA005', $secondId);
    }

    public function test_manually_supplied_unit_id_is_ignored_and_server_generates_its_own(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/units', [
            'id' => 'ZZ999',
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '04',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        $response->assertJsonPath('data.id', 'DA004');
        $this->assertNull(Unit::find('ZZ999'));
    }

    public function test_deleted_unit_code_is_not_reused(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $firstId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '04',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');
        $this->assertSame('DA004', $firstId);

        $this->deleteJson("/api/v1/units/{$firstId}")->assertOk();

        $secondId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '05',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $this->assertSame('DA005', $secondId, 'the deleted DA004 code must not be reused for a new unit');
    }

    public function test_creating_unit_without_resident_succeeds_with_null_resident_id(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '04',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        $response->assertJsonPath('data.resident_id', null);
        $this->assertNull(Unit::find($response->json('data.id'))->resident_id);
    }

    public function test_unit_creation_retries_and_skips_when_generated_code_collides(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        // Simulate two Add Unit requests racing for the same code: the first one already
        // took DA100 by the time this request's transaction commits, so the retry loop in
        // UnitController@store must fall back to the generator's next code (DA101) instead
        // of failing the whole request with a duplicate-key error.
        Unit::factory()->create(['id' => 'DA100', 'cluster_id' => 'DA', 'block' => 'X', 'lot_number' => '01']);

        $this->app->bind(UnitCodeGeneratorService::class, function () {
            return new class extends UnitCodeGeneratorService
            {
                private int $calls = 0;

                public function generate(string $clusterId): string
                {
                    $this->calls++;

                    return $this->calls === 1 ? 'DA100' : 'DA101';
                }
            };
        });

        $response = $this->postJson('/api/v1/units', [
            'cluster_id' => 'DA',
            'block' => 'Z',
            'lot_number' => '06',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        $response->assertJsonPath('data.id', 'DA101');
        $this->assertCount(1, Unit::where('id', 'DA100')->get());
    }
}
