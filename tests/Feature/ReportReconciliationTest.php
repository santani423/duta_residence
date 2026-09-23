<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function as(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * `total_outstanding_principal` must never go negative for a single row, the same floor
     * PenaltyService::calculateInvoiceTotal() applies everywhere else. Under normal application
     * flow discount/principal_paid can never exceed amount (DiscountService and PaymentService
     * both cap against the then-current remaining principal), so this simulates a corrupted/
     * legacy row via a direct write to prove the report still can't report a negative figure.
     */
    public function test_outstanding_principal_never_goes_negative_even_for_a_corrupted_row(): void
    {
        $unit = Unit::factory()->create(['resident_id' => Resident::factory()->create()->id]);
        $finance = User::where('username', 'finance')->firstOrFail();
        $period = Carbon::create(2015, 6, 1);

        $billing = Billing::query()->create([
            'unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month,
            'amount' => 500000, 'status_id' => Billing::STATUS_UNPAID, 'is_penalty_eligible' => false,
            'billing_type' => 'regular', 'created_by' => $finance->id,
        ]);
        // Bypasses the normal discount/payment guards on purpose: discount + principal_paid > amount.
        $billing->forceFill(['discount' => 400000, 'principal_paid' => 200000])->save();

        $this->as('finance');
        $data = $this->getJson("/api/v1/reports/reconciliation?year={$period->year}&month={$period->month}")
            ->assertOk()->json('data');

        $this->assertSame(0.0, (float) $data['total_outstanding_principal']);
    }
}
