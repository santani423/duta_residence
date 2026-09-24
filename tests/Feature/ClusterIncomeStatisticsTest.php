<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\EstateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClusterIncomeStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Only the reference/role/cluster seeders - NOT BillingSeeder/PaymentSeeder, which fill
     * clusters like AL with a large rolling window of demo billing history that would make
     * this test's month-bucket assertions depend on whatever that generator happens to
     * produce for "this month" at run time.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(EstateSeeder::class);
        $this->seed(AdminUserSeeder::class);
    }

    private function as(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Covers: cluster isolation (AL vs BO), only STATUS_PAID counts (unpaid/partial/cancelled
     * excluded), grouping by paid_at (payment date) rather than the billing's own year/month
     * period, months outside the rolling 12-month window are excluded, and months with no paid
     * billing still appear in the series at 0 instead of being dropped.
     */
    public function test_income_statistics_sums_paid_billings_by_payment_month_scoped_to_cluster(): void
    {
        $finance = User::where('username', 'admin.estate')->firstOrFail();

        // A billing is unique per (unit_id, year, month), so each scenario below needs its own
        // unit even where several fall in the same billing period.
        $makeUnit = fn (string $clusterId) => Unit::factory()->create(['cluster_id' => $clusterId, 'resident_id' => Resident::factory()->create()->id]);

        $thisMonth = Carbon::now()->startOfMonth();
        $twoMonthsAgo = $thisMonth->copy()->subMonths(2);
        $outsideWindow = $thisMonth->copy()->subMonths(13);

        // Paid, in-window, cluster AL -> must count in the current month's bucket.
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 20000,
            'paid_at' => $thisMonth->copy()->addDays(2), 'created_by' => $finance->id,
        ]);

        // Paid, in-window, cluster AL, but its OWN billing period is 5 months ago while it was
        // only actually paid (paid_at) two months ago - must bucket by paid_at, not year/month.
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $thisMonth->copy()->subMonths(5)->year, 'month' => $thisMonth->copy()->subMonths(5)->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 0,
            'paid_at' => $twoMonthsAgo->copy()->addDays(5), 'created_by' => $finance->id,
        ]);

        // Paid, in-window, but a DIFFERENT cluster -> must not leak into AL's total.
        Billing::factory()->create([
            'unit_id' => $makeUnit('BO')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 500000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 500000, 'penalty_paid' => 0,
            'paid_at' => $thisMonth->copy()->addDays(1), 'created_by' => $finance->id,
        ]);

        // Unpaid / partial / cancelled, cluster AL, in-window -> must be excluded from income.
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_UNPAID, 'created_by' => $finance->id,
        ]);
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PARTIAL,
            'principal_paid' => 175000, 'paid_at' => null, 'created_by' => $finance->id,
        ]);
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_CANCELLED,
            'principal_paid' => 0, 'penalty_paid' => 0, 'paid_at' => null, 'created_by' => $finance->id,
        ]);

        // Paid, cluster AL, but OUTSIDE the rolling 12-month window -> must be excluded.
        Billing::factory()->create([
            'unit_id' => $makeUnit('AL')->id, 'year' => $outsideWindow->year, 'month' => $outsideWindow->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 0,
            'paid_at' => $outsideWindow->copy()->addDays(3), 'created_by' => $finance->id,
        ]);

        $this->as('admin.estate');
        $data = $this->getJson('/api/v1/clusters/AL/income-statistics')->assertOk()->json('data');

        $this->assertSame('AL', $data['cluster_id']);
        $this->assertCount(12, $data['monthly_income']);

        $byMonth = collect($data['monthly_income'])->keyBy('month');
        $this->assertSame(370000.0, (float) $byMonth[$thisMonth->format('Y-m')]['income']);
        $this->assertSame(350000.0, (float) $byMonth[$twoMonthsAgo->format('Y-m')]['income']);
        $this->assertSame(720000.0, (float) $data['total_income']);

        // Every other of the 12 months has no paid billing and must still appear, at 0.
        $zeroMonths = collect($data['monthly_income'])
            ->reject(fn ($row) => in_array($row['month'], [$thisMonth->format('Y-m'), $twoMonthsAgo->format('Y-m')], true));
        $this->assertCount(10, $zeroMonths);
        $this->assertTrue($zeroMonths->every(fn ($row) => (float) $row['income'] === 0.0));
    }

    public function test_income_statistics_requires_cluster_view_permission(): void
    {
        $this->getJson('/api/v1/clusters/AL/income-statistics')->assertUnauthorized();
    }
}
