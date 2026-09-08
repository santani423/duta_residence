<?php

namespace Tests\Unit;

use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Services\ClusterRateScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClusterRateScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeCluster(): Cluster
    {
        return Cluster::query()->create([
            'id' => 'ZZ',
            'name' => 'Cluster Uji',
            'monthly_rate' => 0,
            'is_active' => true,
        ]);
    }

    private function schedule(Cluster $cluster, string $effectiveDate, float $rate): ClusterRateSchedule
    {
        return ClusterRateSchedule::query()->create([
            'cluster_id' => $cluster->id,
            'effective_date' => $effectiveDate,
            'rate' => $rate,
            'is_active' => true,
        ]);
    }

    public function test_it_uses_the_ipl_nominal_from_one_month_before_the_selected_period(): void
    {
        $cluster = $this->makeCluster();
        $this->schedule($cluster, '2026-08-01', 300000);

        $result = app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);

        $this->assertSame(300000.0, $result['rate']);
        $this->assertSame(2026, $result['source_year']);
        $this->assertSame(8, $result['source_month']);
    }

    public function test_it_falls_back_two_months_back_when_the_previous_month_has_no_ipl(): void
    {
        $cluster = $this->makeCluster();
        $this->schedule($cluster, '2026-07-01', 275000);
        // August (the month right before the selected period) has no schedule of its own.

        $result = app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);

        $this->assertSame(275000.0, $result['rate']);
        $this->assertSame(2026, $result['source_year']);
        $this->assertSame(7, $result['source_month']);
    }

    public function test_it_keeps_searching_backward_across_several_empty_months(): void
    {
        $cluster = $this->makeCluster();
        $this->schedule($cluster, '2026-06-01', 250000);
        // July and August both have no schedule of their own.

        $result = app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);

        $this->assertSame(250000.0, $result['rate']);
        $this->assertSame(2026, $result['source_year']);
        $this->assertSame(6, $result['source_month']);
    }

    public function test_it_throws_instead_of_defaulting_to_zero_when_no_earlier_period_has_an_ipl_nominal(): void
    {
        $cluster = $this->makeCluster();

        $this->expectException(ValidationException::class);
        app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);
    }

    public function test_it_never_uses_an_ipl_nominal_configured_after_the_selected_period(): void
    {
        $cluster = $this->makeCluster();
        $this->schedule($cluster, '2026-06-01', 200000);
        $this->schedule($cluster, '2026-10-01', 999000); // effective after the selected period - must be ignored

        $result = app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);

        $this->assertSame(200000.0, $result['rate']);
        $this->assertSame(6, $result['source_month']);
    }

    public function test_it_ignores_inactive_schedules_when_walking_backward(): void
    {
        $cluster = $this->makeCluster();
        $this->schedule($cluster, '2026-05-01', 150000);
        ClusterRateSchedule::query()->create([
            'cluster_id' => $cluster->id,
            'effective_date' => '2026-07-01',
            'rate' => 500000,
            'is_active' => false,
        ]);

        $result = app(ClusterRateScheduleService::class)->resolveRateForBackdatedPeriod($cluster, 2026, 9);

        $this->assertSame(150000.0, $result['rate']);
        $this->assertSame(5, $result['source_month']);
    }
}
