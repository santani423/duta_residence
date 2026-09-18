<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EstateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BackBillingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);
    }

    private function makeAuthorizedUser(): User
    {
        Permission::findOrCreate('billings.prepare-back');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('billings.prepare-back');

        return $user;
    }

    private function makeUnit(): Unit
    {
        $cluster = Cluster::query()->create([
            'id' => 'ZZ',
            'name' => 'Cluster Uji',
            'monthly_rate' => 0,
            'is_active' => true,
        ]);
        $resident = Resident::factory()->create();

        return Unit::factory()->create(['cluster_id' => $cluster->id, 'resident_id' => $resident->id]);
    }

    /** @return array{year: int, month: int} first month back-billing may cover for a unit without billings. */
    private function startPeriod(): array
    {
        $next = now()->startOfMonth()->addMonthNoOverflow();

        return ['year' => $next->year, 'month' => $next->month];
    }

    public function test_a_loket_role_user_can_create_a_backdated_billing(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        $loket = User::factory()->create(['is_active' => true]);
        $loket->assignRole('loket');

        Sanctum::actingAs($loket);

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod()],
        ])->assertCreated()->assertJsonPath('data.0.amount', '320000.00');
    }

    public function test_backdated_billing_uses_the_ipl_nominal_from_the_month_before_the_selected_period(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $response = $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod()],
        ])->assertCreated();

        $response->assertJsonPath('data.0.amount', '320000.00');
        $response->assertJsonPath('data.0.billing_type', 'back');
        $this->assertDatabaseHas('billings', [
            'unit_id' => $unit->id,
            ...$this->startPeriod(),
            'amount' => 320000,
            'billing_type' => 'back',
        ]);
    }

    public function test_backdated_billing_walks_backward_when_the_previous_month_has_no_ipl_configured(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-06-01',
            'rate' => 280000,
            'is_active' => true,
        ]);
        // July and August (right before the selected September period) have no schedule.

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod()],
        ])->assertCreated()->assertJsonPath('data.0.amount', '280000.00');
    }

    public function test_backdated_billing_is_rejected_when_no_prior_period_has_an_ipl_nominal_configured(): void
    {
        $unit = $this->makeUnit();

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod()],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('billings', ['unit_id' => $unit->id]);
    }

    public function test_a_manually_submitted_amount_is_ignored_and_never_persisted(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod() + ['amount' => 1]],
        ])->assertCreated()->assertJsonPath('data.0.amount', '320000.00');
    }

    public function test_back_preview_returns_the_read_only_resolved_nominal_without_creating_a_billing(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->getJson('/api/v1/billings/back-preview?'.http_build_query(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertJsonPath('data.rate', 320000)
            ->assertJsonPath('data.source_year', 2026)
            ->assertJsonPath('data.source_month', 8);

        $this->assertSame(0, Billing::where('unit_id', $unit->id)->count());
    }

    public function test_back_preview_returns_a_clear_error_when_nothing_is_configured(): void
    {
        $unit = $this->makeUnit();

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->getJson('/api/v1/billings/back-preview?'.http_build_query(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9]))
            ->assertStatus(422);
    }

    public function test_prepare_back_is_rejected_for_an_inactive_unit(): void
    {
        $unit = $this->makeUnit();
        $unit->update(['status_id' => 'TA']);
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [$this->startPeriod()],
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_id']);

        $this->assertSame(0, Billing::where('unit_id', $unit->id)->count());
    }

    public function test_back_preview_is_rejected_for_an_inactive_unit(): void
    {
        $unit = $this->makeUnit();
        $unit->update(['status_id' => 'TA']);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->getJson('/api/v1/billings/back-preview?'.http_build_query(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9]))
            ->assertStatus(422)->assertJsonValidationErrors(['unit_id']);
    }

    public function test_one_invalid_period_creates_nothing_for_the_whole_backdated_batch(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        // The first month is fine but the second goes backwards, so no billing may be created.
        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [
                $this->startPeriod(),
                ['year' => 2026, 'month' => 6],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Billing::where('unit_id', $unit->id)->count());
    }

    public function test_back_range_starts_the_month_after_the_units_last_billing_and_after_each_back_billing(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id, 'effective_date' => '2026-01-01', 'rate' => 300000, 'is_active' => true,
        ]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->getJson("/api/v1/billings/back-range?unit_id={$unit->id}")->assertOk()
            ->assertJsonPath('data.last_billed_period', ['year' => 2026, 'month' => 9])
            ->assertJsonPath('data.start_period', ['year' => 2026, 'month' => 10]);

        // Back-bill Oct-Nov; the next range must then start in December.
        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [['year' => 2026, 'month' => 10], ['year' => 2026, 'month' => 11]],
        ])->assertCreated();

        $this->getJson("/api/v1/billings/back-range?unit_id={$unit->id}")
            ->assertJsonPath('data.start_period', ['year' => 2026, 'month' => 12]);

        // December 2026 -> January 2027 crosses the year boundary.
        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [['year' => 2026, 'month' => 12], ['year' => 2027, 'month' => 1]],
        ])->assertCreated();
        $this->getJson("/api/v1/billings/back-range?unit_id={$unit->id}")
            ->assertJsonPath('data.start_period', ['year' => 2027, 'month' => 2]);
    }

    public function test_back_range_for_a_unit_without_billings_starts_next_month_and_has_no_last_period(): void
    {
        $unit = $this->makeUnit();

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->getJson("/api/v1/billings/back-range?unit_id={$unit->id}")->assertOk()
            ->assertJsonPath('data.last_billed_period', null)
            ->assertJsonPath('data.start_period', $this->startPeriod());
    }

    public function test_prepare_back_rejects_a_start_before_or_after_the_expected_month_and_gaps(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id, 'effective_date' => '2026-01-01', 'rate' => 300000, 'is_active' => true,
        ]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        // Overlaps an already billed month.
        $this->postJson('/api/v1/billings/prepare-back', ['unit_id' => $unit->id, 'periods' => [['year' => 2026, 'month' => 9]]])->assertStatus(422);
        // Skips October.
        $this->postJson('/api/v1/billings/prepare-back', ['unit_id' => $unit->id, 'periods' => [['year' => 2026, 'month' => 11]]])->assertStatus(422);
        // Right start but a gap in the middle.
        $this->postJson('/api/v1/billings/prepare-back', ['unit_id' => $unit->id, 'periods' => [['year' => 2026, 'month' => 10], ['year' => 2026, 'month' => 12]]])->assertStatus(422);

        $this->assertSame(1, Billing::where('unit_id', $unit->id)->count());
    }
}
