<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Models\PenaltyRule;
use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        collect([
            ['name' => 'current', 'min' => 0, 'max' => 0, 'amount' => 0],
            ['name' => 'tier1', 'min' => 1, 'max' => 2, 'amount' => 15000],
            ['name' => 'tier2', 'min' => 3, 'max' => null, 'amount' => 30000],
        ])->each(fn (array $rule) => PenaltyRule::query()->create([
            'name' => $rule['name'],
            'cluster_id' => null,
            'minimum_overdue_month' => $rule['min'],
            'maximum_overdue_month' => $rule['max'],
            'penalty_amount' => $rule['amount'],
            'is_active' => true,
            'effective_start_date' => '2020-01-01',
        ]));
    }

    private function billingForMonthsAgo(int $monthsAgo, Carbon $now, float $amount = 500000): Billing
    {
        $period = $now->copy()->startOfMonth()->subMonths($monthsAgo);

        return new Billing([
            'unit_id' => 'AL001',
            'year' => $period->year,
            'month' => $period->month,
            'amount' => $amount,
            'is_penalty_eligible' => true,
            'status_id' => Billing::STATUS_UNPAID,
        ]);
    }

    public function test_penalty_tiers_match_business_rule(): void
    {
        $service = app(PenaltyService::class);
        $now = Carbon::create(2026, 12, 15);

        $this->assertSame(0.0, $service->calculatePenalty($this->billingForMonthsAgo(0, $now), $now));
        $this->assertSame(15000.0, $service->calculatePenalty($this->billingForMonthsAgo(1, $now), $now));
        $this->assertSame(15000.0, $service->calculatePenalty($this->billingForMonthsAgo(2, $now), $now));
        $this->assertSame(30000.0, $service->calculatePenalty($this->billingForMonthsAgo(3, $now), $now));
        $this->assertSame(30000.0, $service->calculatePenalty($this->billingForMonthsAgo(4, $now), $now));
        $this->assertSame(30000.0, $service->calculatePenalty($this->billingForMonthsAgo(12, $now), $now));
    }

    public function test_overdue_months_uses_calendar_month_diff_not_days(): void
    {
        $service = app(PenaltyService::class);
        $billing = new Billing(['year' => 2026, 'month' => 12]);

        $this->assertSame(0, $service->calculateOverdueMonths($billing, Carbon::create(2026, 12, 1)));
        $this->assertSame(0, $service->calculateOverdueMonths($billing, Carbon::create(2026, 12, 31)));
    }

    public function test_overdue_months_crosses_year_boundary(): void
    {
        $service = app(PenaltyService::class);
        $billing = new Billing(['year' => 2026, 'month' => 12]);

        $this->assertSame(1, $service->calculateOverdueMonths($billing, Carbon::create(2027, 1, 5)));
    }

    public function test_overdue_months_never_negative(): void
    {
        $service = app(PenaltyService::class);
        $billing = new Billing(['year' => 2026, 'month' => 12]);

        $this->assertSame(0, $service->calculateOverdueMonths($billing, Carbon::create(2026, 6, 1)));
    }

    public function test_paid_billing_returns_frozen_penalty_not_dynamic(): void
    {
        $service = app(PenaltyService::class);
        $now = Carbon::create(2026, 12, 15);
        $billing = $this->billingForMonthsAgo(5, $now);
        $billing->status_id = Billing::STATUS_PAID;
        $billing->penalty = 15000; // dibayar saat masih tier 1-2 bulan, walau sekarang sudah tier 3+

        $this->assertSame(15000.0, $service->calculatePenalty($billing, $now));
    }

    public function test_cancelled_billing_has_zero_penalty(): void
    {
        $service = app(PenaltyService::class);
        $now = Carbon::create(2026, 12, 15);
        $billing = $this->billingForMonthsAgo(6, $now);
        $billing->status_id = Billing::STATUS_CANCELLED;

        $this->assertSame(0.0, $service->calculatePenalty($billing, $now));
    }

    public function test_ineligible_unit_has_zero_penalty_even_when_overdue(): void
    {
        $service = app(PenaltyService::class);
        $now = Carbon::create(2026, 12, 15);
        $billing = $this->billingForMonthsAgo(4, $now);
        $billing->is_penalty_eligible = false;

        $this->assertSame(0.0, $service->calculatePenalty($billing, $now));
    }

    public function test_july_to_december_example_matches_specification(): void
    {
        $service = app(PenaltyService::class);
        $now = Carbon::create(2026, 12, 10);
        $expected = [7 => 5, 8 => 4, 9 => 3, 10 => 2, 11 => 1, 12 => 0];
        $expectedPenalty = [7 => 30000.0, 8 => 30000.0, 9 => 30000.0, 10 => 15000.0, 11 => 15000.0, 12 => 0.0];

        $totalPrincipal = 0.0;
        $totalPenalty = 0.0;

        foreach ($expected as $month => $expectedOverdue) {
            $billing = new Billing([
                'unit_id' => 'AL001', 'year' => 2026, 'month' => $month,
                'amount' => 500000, 'is_penalty_eligible' => true, 'status_id' => Billing::STATUS_UNPAID,
            ]);

            $calc = $service->calculateInvoiceTotal($billing, $now);
            $this->assertSame($expectedOverdue, $calc['overdue_months'], "overdue_months for month {$month}");
            $this->assertSame($expectedPenalty[$month], $calc['penalty_amount'], "penalty for month {$month}");
            $this->assertSame(500000.0 + $expectedPenalty[$month], $calc['total_amount'], "total for month {$month}");

            $totalPrincipal += $calc['principal_amount'];
            $totalPenalty += $calc['penalty_amount'];
        }

        $this->assertSame(3000000.0, $totalPrincipal);
        $this->assertSame(120000.0, $totalPenalty);
        $this->assertSame(3120000.0, $totalPrincipal + $totalPenalty);
    }
}
