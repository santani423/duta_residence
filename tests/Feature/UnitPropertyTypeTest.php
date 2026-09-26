<?php

namespace Tests\Feature;

use App\Models\PropertyType;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnitPropertyTypeTest extends TestCase
{
    use RefreshDatabase;

    private function unitPayload(array $overrides = []): array
    {
        return array_merge([
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '91',
            'property_type_id' => 'K',
            'status_id' => 'TA',
        ], $overrides);
    }

    public function test_only_bangunan_kavling_and_ruko_are_seeded(): void
    {
        $this->seed();

        $this->assertSame(
            ['B' => 'Bangunan', 'K' => 'Kavling', 'R' => 'Ruko'],
            PropertyType::orderBy('id')->pluck('name', 'id')->all(),
        );
    }

    public function test_legacy_kavling_penghuni_code_is_normalized_to_kavling_on_create(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $unitId = $this->postJson('/api/v1/units', $this->unitPayload(['property_type_id' => 'P']))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame('K', Unit::find($unitId)->property_type_id);
    }

    public function test_unknown_property_type_is_rejected(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $this->postJson('/api/v1/units', $this->unitPayload(['property_type_id' => 'X']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('property_type_id');
    }

    public function test_filter_accepts_kavling_and_legacy_codes(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $kavlingCount = Unit::where('property_type_id', 'K')->count();
        $this->assertGreaterThan(0, $kavlingCount);

        foreach (['K', 'P', 'K,P'] as $filter) {
            $response = $this->getJson('/api/v1/units?per_page=1000&property_type_id='.urlencode($filter))->assertOk();
            $types = collect($response->json('data'))->pluck('property_type_id')->unique()->values()->all();

            $this->assertSame(['K'], $types, "filter {$filter}");
            $this->assertCount($kavlingCount, $response->json('data'), "filter {$filter}");
        }
    }

    public function test_any_kavling_unit_can_be_converted_to_bangunan(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $unit = Unit::factory()->create(['property_type_id' => 'K', 'resident_id' => null, 'status_id' => 'TA']);

        $this->postJson("/api/v1/units/{$unit->id}/convert-property", ['property_type_id' => 'B'])->assertOk();

        $this->assertSame('B', $unit->refresh()->property_type_id);
    }

    public function test_merge_migration_moves_kavling_penghuni_units_to_kavling(): void
    {
        $this->seed();

        DB::table('property_types')->where('id', 'K')->update(['name' => 'Kavling Developer']);
        DB::table('property_types')->insert(['id' => 'P', 'name' => 'Kavling Penghuni']);
        $legacy = Unit::factory()->create(['property_type_id' => 'P', 'resident_id' => null, 'status_id' => 'TA']);
        $trashed = Unit::factory()->create(['property_type_id' => 'P', 'resident_id' => null, 'status_id' => 'TA']);
        $trashed->delete();

        $migration = require database_path('migrations/2026_09_26_000001_merge_kavling_property_types.php');
        $migration->up();

        $this->assertSame('K', $legacy->refresh()->property_type_id);
        $this->assertSame('K', Unit::withTrashed()->find($trashed->id)->property_type_id);
        $this->assertNull(PropertyType::find('P'));
        $this->assertSame('Kavling', PropertyType::find('K')->name);
    }
}
