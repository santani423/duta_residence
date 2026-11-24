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

    public function test_billing_list_is_sorted_by_latest_period_then_latest_update(): void
    {
        $this->seed(EstateSeeder::class);

        $unit = Unit::factory()->create(['cluster_id' => 'AL']);

        // Inserted out of period order on purpose, so a created_at-based sort would differ.
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 10]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 2]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 3]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 1]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2025, 'month' => 12]);

        Permission::findOrCreate('billings.view');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('billings.view');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/billings?unit_id={$unit->id}&per_page=10")->assertOk();

        $periods = collect($response->json('data'))->map(fn ($row) => [$row['year'], $row['month']])->values()->all();

        $this->assertSame([
            [2026, 10],
            [2026, 3],
            [2026, 2],
            [2026, 1],
            [2025, 12],
        ], $periods);
    }

    public function test_billing_list_puts_most_recently_updated_first_within_same_period(): void
    {
        $this->seed(EstateSeeder::class);

        $unitA = Unit::factory()->create(['cluster_id' => 'AL']);
        $unitB = Unit::factory()->create(['cluster_id' => 'AL']);

        $older = Billing::factory()->create(['unit_id' => $unitA->id, 'year' => 2026, 'month' => 5, 'updated_at' => now()->subDay()]);
        $newer = Billing::factory()->create(['unit_id' => $unitB->id, 'year' => 2026, 'month' => 5, 'updated_at' => now()]);

        Permission::findOrCreate('billings.view');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('billings.view');
        Sanctum::actingAs($user);

        $ids = collect($this->getJson('/api/v1/billings?year=2026&month=5&per_page=10')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_paid_billing_overdue_months_is_frozen_at_paid_month(): void
    {
        $this->seed(EstateSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 25));

        $unit = Unit::factory()->create(['cluster_id' => 'AL']);
        $paid = Billing::factory()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 5,
            'status_id' => Billing::STATUS_PAID, 'paid_at' => now()->setDate(2026, 6, 10),
        ]);
        $unpaid = Billing::factory()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 6, 'status_id' => Billing::STATUS_UNPAID,
        ]);

        Permission::findOrCreate('billings.view');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('billings.view');
        Sanctum::actingAs($user);

        $rows = collect($this->getJson("/api/v1/billings?unit_id={$unit->id}&per_page=10")->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(1, $rows[$paid->id]['penalty_detail']['overdue_months']);
        $this->assertSame(5, $rows[$unpaid->id]['penalty_detail']['overdue_months']);

        $filtered = collect($this->getJson("/api/v1/billings?unit_id={$unit->id}&max_overdue_months=1&per_page=10")->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$paid->id], $filtered);
    }
}
