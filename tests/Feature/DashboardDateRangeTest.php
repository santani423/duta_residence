<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Receipt;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\EstateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDateRangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(EstateSeeder::class);
        $this->seed(AdminUserSeeder::class);
        // Token sesi aktif (bukan Sanctum::actingAs) agar lolos middleware single-session.
        $this->withToken($this->apiTokenFor(User::where('username', 'admin.estate')->firstOrFail()));
    }

    private function receipt(string $number, Unit $unit, string $transactionDate, int $amount): void
    {
        Receipt::create([
            'number' => $number,
            'unit_id' => $unit->id,
            'transaction_date' => $transactionDate,
            'resident_name' => 'Tes',
            'cluster_name' => $unit->cluster_id,
            'block' => $unit->block,
            'lot_number' => $unit->lot_number,
            'total_billing' => $amount,
            'grand_total' => $amount,
            'payment_method_id' => 'C',
            'status' => 'paid',
        ]);
    }

    public function test_receipts_total_counts_only_transactions_inside_the_range(): void
    {
        $unit = Unit::factory()->create(['cluster_id' => 'D', 'resident_id' => Resident::factory()->create()->id]);
        $this->receipt('GD.2026.10.000001', $unit, '2026-10-01 00:00:00', 100000);
        $this->receipt('GD.2026.10.000002', $unit, '2026-10-31 23:30:00', 200000);
        $this->receipt('GD.2026.11.000001', $unit, '2026-11-01 08:00:00', 400000);

        $data = $this->getJson('/api/v1/reports/dashboard?start=2026-10-01&end=2026-10-31')->assertOk()->json('data');

        $this->assertEquals(300000, (float) $data['period_receipts_total']);
        $this->assertSame(['start' => '2026-10-01', 'end' => '2026-10-31'], $data['period']);
    }

    public function test_dashboard_rejects_an_end_date_before_the_start(): void
    {
        $this->getJson('/api/v1/reports/dashboard?start=2026-10-31&end=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    public function test_monthly_by_range_includes_every_billing_month_inside_it(): void
    {
        $unit = Unit::factory()->create(['cluster_id' => 'D', 'resident_id' => Resident::factory()->create()->id]);
        foreach ([[2026, 9, 300000], [2026, 10, 350000], [2026, 11, 400000]] as [$year, $month, $amount]) {
            Billing::factory()->create(['unit_id' => $unit->id, 'year' => $year, 'month' => $month, 'amount' => $amount]);
        }

        $oct = $this->getJson('/api/v1/reports/monthly?start=2026-10-01&end=2026-10-31')->assertOk()->json('data');
        $this->assertCount(1, $oct);
        $this->assertSame('D', $oct[0]['cluster_id']);
        $this->assertEquals(350000, (float) $oct[0]['total_billing']);

        // 15 Sep - 3 Okt mencakup periode September dan Oktober, bukan November.
        $span = $this->getJson('/api/v1/reports/monthly?start=2026-09-15&end=2026-10-03')->assertOk()->json('data');
        $this->assertSame(2, (int) $span[0]['billing_count']);
        $this->assertEquals(650000, (float) $span[0]['total_billing']);
    }

    public function test_monthly_without_range_still_uses_a_single_billing_period(): void
    {
        $unit = Unit::factory()->create(['cluster_id' => 'D', 'resident_id' => Resident::factory()->create()->id]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9, 'amount' => 300000]);
        Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 10, 'amount' => 350000]);

        $data = $this->getJson('/api/v1/reports/monthly?year=2026&month=9')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals(300000, (float) $data[0]['total_billing']);
    }
}
