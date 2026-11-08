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
use Tests\TestCase;

class ClusterIncomeStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Only the reference/role/cluster seeders - NOT BillingSeeder/PaymentSeeder, which fill
     * clusters like D with a large rolling window of demo billing history that would make
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

    /**
     * Token lewat jalur login-single-session (apiTokenFor), bukan Sanctum::actingAs: token test
     * tanpa sesi aktif akan ditolak middleware single-session (401 SESSION_REVOKED).
     */
    private function as(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->withToken($this->apiTokenFor($user));

        return $user;
    }

    /**
     * Covers: cluster isolation (D vs G), only STATUS_PAID counts (unpaid/partial/cancelled
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

        // Paid, in-window, cluster D -> must count in the current month's bucket.
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 20000,
            'paid_at' => $thisMonth->copy()->addDays(2), 'created_by' => $finance->id,
        ]);

        // Paid, in-window, cluster D, but its OWN billing period is 5 months ago while it was
        // only actually paid (paid_at) two months ago - must bucket by paid_at, not year/month.
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $thisMonth->copy()->subMonths(5)->year, 'month' => $thisMonth->copy()->subMonths(5)->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 0,
            'paid_at' => $twoMonthsAgo->copy()->addDays(5), 'created_by' => $finance->id,
        ]);

        // Paid, in-window, but a DIFFERENT cluster -> must not leak into D's total.
        Billing::factory()->create([
            'unit_id' => $makeUnit('G')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 500000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 500000, 'penalty_paid' => 0,
            'paid_at' => $thisMonth->copy()->addDays(1), 'created_by' => $finance->id,
        ]);

        // Unpaid / partial / cancelled, cluster D, in-window -> must be excluded from income.
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_UNPAID, 'created_by' => $finance->id,
        ]);
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PARTIAL,
            'principal_paid' => 175000, 'paid_at' => null, 'created_by' => $finance->id,
        ]);
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $thisMonth->year, 'month' => $thisMonth->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_CANCELLED,
            'principal_paid' => 0, 'penalty_paid' => 0, 'paid_at' => null, 'created_by' => $finance->id,
        ]);

        // Paid, cluster D, but OUTSIDE the rolling 12-month window -> must be excluded.
        Billing::factory()->create([
            'unit_id' => $makeUnit('D')->id, 'year' => $outsideWindow->year, 'month' => $outsideWindow->month,
            'amount' => 350000, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => 350000, 'penalty_paid' => 0,
            'paid_at' => $outsideWindow->copy()->addDays(3), 'created_by' => $finance->id,
        ]);

        $this->as('admin.estate');
        $data = $this->getJson('/api/v1/clusters/D/income-statistics')->assertOk()->json('data');

        $this->assertSame('D', $data['cluster_id']);
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

    /**
     * Rentang tanggal kustom: batas awal & akhir inklusif (pelunasan tepat di start/end ikut),
     * bulan parsial di ujung hanya menghitung pelunasan di dalam rentang, dan total = jumlah
     * seluruh pelunasan dalam rentang.
     */
    public function test_income_statistics_filters_by_custom_date_range(): void
    {
        $finance = User::where('username', 'admin.estate')->firstOrFail();
        $makeUnit = fn (string $clusterId) => Unit::factory()->create(['cluster_id' => $clusterId, 'resident_id' => Resident::factory()->create()->id]);

        $paid = fn (string $clusterId, int $year, int $month, int $amount, string $paidAt) => Billing::factory()->create([
            'unit_id' => $makeUnit($clusterId)->id, 'year' => $year, 'month' => $month,
            'amount' => $amount, 'status_id' => Billing::STATUS_PAID,
            'principal_paid' => $amount, 'penalty_paid' => 0,
            'paid_at' => $paidAt, 'created_by' => $finance->id,
        ]);

        $paid('D', 2024, 2, 900000, '2024-02-29 23:59:00');   // sebelum rentang -> tidak masuk
        $paid('D', 2024, 3, 200000, '2024-03-10 08:00:00');   // tepat di start -> masuk (Mar)
        $paid('D', 2024, 4, 300000, '2024-04-15 12:00:00');   // Apr -> masuk
        $paid('D', 2024, 5, 400000, '2024-05-20 23:30:00');   // tepat di end -> masuk (May)
        $paid('D', 2024, 5, 500000, '2024-05-21 00:10:00');   // sesudah rentang -> tidak masuk
        $paid('G', 2024, 4, 600000, '2024-04-15 12:00:00');   // cluster lain -> tidak masuk

        $this->as('admin.estate');
        $data = $this->getJson('/api/v1/clusters/D/income-statistics?start=2024-03-10&end=2024-05-20')
            ->assertOk()
            ->json('data');

        $this->assertSame('2024-03-10', $data['period']['start']);
        $this->assertSame('2024-05-20', $data['period']['end']);
        $this->assertCount(3, $data['monthly_income']);

        $byMonth = collect($data['monthly_income'])->keyBy('month');
        $this->assertSame(200000.0, (float) $byMonth['2024-03']['income']);
        $this->assertSame(300000.0, (float) $byMonth['2024-04']['income']);
        $this->assertSame(400000.0, (float) $byMonth['2024-05']['income']);
        $this->assertSame(900000.0, (float) $data['total_income']);
    }

    public function test_income_statistics_rejects_end_before_start(): void
    {
        $this->as('admin.estate');

        $this->getJson('/api/v1/clusters/D/income-statistics?start=2024-05-01&end=2024-04-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    public function test_income_statistics_rejects_range_longer_than_max_months(): void
    {
        $this->as('admin.estate');

        // 2019-01 s/d 2024-12 = 72 bulan, melebihi batas 60 bulan.
        $this->getJson('/api/v1/clusters/D/income-statistics?start=2019-01-01&end=2024-12-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start']);
    }

    public function test_income_statistics_requires_cluster_view_permission(): void
    {
        $this->getJson('/api/v1/clusters/D/income-statistics')->assertUnauthorized();
    }
}
