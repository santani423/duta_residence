<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BillingIndexSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_list_is_sorted_chronologically_by_period_not_creation_order(): void
    {
        $this->seed(EstateSeeder::class);

        $unit = Unit::factory()->create(['cluster_id' => 'AL']);

        // Inserted out of chronological order on purpose, so a created_at-based sort
        // would return them in a different order than the expected 2026-01..2026-04.
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 10]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 2]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 3]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 1]);

        Permission::findOrCreate('billings.view');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('billings.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/billings?unit_id={$unit->id}&per_page=10")->assertOk();

        $periods = collect($response->json('data'))->map(fn ($row) => [$row['year'], $row['month']])->values()->all();

        $this->assertSame([
            [2026, 1],
            [2026, 2],
            [2026, 3],
            [2026, 10],
        ], $periods);
    }
}
