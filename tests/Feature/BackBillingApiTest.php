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
            'periods' => [['year' => 2026, 'month' => 9]],
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
            'periods' => [['year' => 2026, 'month' => 9]],
        ])->assertCreated();

        $response->assertJsonPath('data.0.amount', '320000.00');
        $response->assertJsonPath('data.0.billing_type', 'back');
        $this->assertDatabaseHas('billings', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
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
            'periods' => [['year' => 2026, 'month' => 9]],
        ])->assertCreated()->assertJsonPath('data.0.amount', '280000.00');
    }

    public function test_backdated_billing_is_rejected_when_no_prior_period_has_an_ipl_nominal_configured(): void
    {
        $unit = $this->makeUnit();

        Sanctum::actingAs($this->makeAuthorizedUser());

        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [['year' => 2026, 'month' => 9]],
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
            'periods' => [['year' => 2026, 'month' => 9, 'amount' => 1]],
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

    public function test_one_invalid_period_rolls_back_the_whole_backdated_batch(): void
    {
        $unit = $this->makeUnit();
        ClusterRateSchedule::query()->create([
            'cluster_id' => $unit->cluster_id,
            'effective_date' => '2026-08-01',
            'rate' => 320000,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->makeAuthorizedUser());

        // September resolves fine (August is configured), but June has nothing before it.
        $this->postJson('/api/v1/billings/prepare-back', [
            'unit_id' => $unit->id,
            'periods' => [
                ['year' => 2026, 'month' => 9],
                ['year' => 2026, 'month' => 6],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Billing::where('unit_id', $unit->id)->count());
    }
}
