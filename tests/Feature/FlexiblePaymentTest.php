<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\Reversal;
use App\Models\Unit;
use App\Models\UnitDeposit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ReversalService;
use App\Services\UnitBalanceLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlexiblePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeUnit(): Unit
    {
        $resident = Resident::factory()->create();

        return Unit::factory()->create([
            'resident_id' => $resident->id,
            'is_penalty_eligible' => false,
        ]);
    }

    /**
     * Penalty-free, round-number billing so payment math matches the spec examples exactly
     * regardless of when the test suite runs.
     */
    private function makeBilling(Unit $unit, float $amount, int $monthOffset = 0): Billing
    {
        $finance = User::where('username', 'finance')->firstOrFail();
        $period = now()->startOfMonth()->addMonths($monthOffset);

        return Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => $period->year,
            'month' => $period->month,
            'amount' => $amount,
            'status_id' => Billing::STATUS_UNPAID,
            'is_penalty_eligible' => false,
            'billing_type' => 'regular',
            'approved_by' => $finance->id,
            'approved_at' => now()->subDay(),
            'created_by' => $finance->id,
        ]);
    }

    public function test_exact_payment_settles_all_bills(): void
    {
        $unit = $this->makeUnit();
        $this->makeBilling($unit, 900000);
        Sanctum::actingAs(User::where('username', 'loket')->firstOrFail());

        $response = $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id,
            'amount' => 900000,
            'payment_method_id' => 'C',
        ])->assertCreated();

        $this->assertSame(0.0, (float) $response->json('data.deposit_amount'));
        $this->assertSame(0, Billing::where('unit_id', $unit->id)->where('status_id', '!=', Billing::STATUS_PAID)->count());
        $this->assertSame(0.0, (float) $unit->fresh()->balance);
    }

    public function test_underpayment_leaves_remaining_outstanding(): void
    {
        $unit = $this->makeUnit();
        $this->makeBilling($unit, 900000);
        $loket = User::where('username', 'loket')->firstOrFail();

        $receipt = app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 500000], $loket->id);

        $billing = Billing::where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame(500000.0, (float) $receipt->total_billing);
        $this->assertSame(Billing::STATUS_PARTIAL, $billing->status_id);
        $this->assertSame(500000.0, (float) $billing->principal_paid);
        $this->assertSame(400000.0, round(900000 - (float) $billing->principal_paid, 2));
        $this->assertSame(0.0, (float) $unit->fresh()->balance);
    }

    public function test_overpayment_credits_unit_balance(): void
    {
        $unit = $this->makeUnit();
        $this->makeBilling($unit, 900000);
        $loket = User::where('username', 'loket')->firstOrFail();

        $receipt = app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 1000000], $loket->id);

        $this->assertSame(900000.0, (float) $receipt->total_billing);
        $this->assertSame(100000.0, (float) $receipt->deposit_amount);
        $this->assertSame(100000.0, (float) $unit->fresh()->balance);
        $this->assertSame(Billing::STATUS_PAID, Billing::where('unit_id', $unit->id)->firstOrFail()->status_id);
        $this->assertDatabaseHas('unit_deposits', [
            'unit_id' => $unit->id,
            'type' => UnitDeposit::TYPE_PAYMENT_OVERPAYMENT,
            'direction' => 'credit',
            'amount' => 100000,
        ]);
    }

    public function test_existing_balance_is_used_before_new_cash(): void
    {
        $unit = $this->makeUnit();
        $this->makeBilling($unit, 300000);
        $loket = User::where('username', 'loket')->firstOrFail();

        app(UnitBalanceLedgerService::class)->credit($unit, UnitDeposit::TYPE_MANUAL_CREDIT, 100000, [
            'notes' => 'saldo awal skenario pengujian', 'created_by' => $loket->id,
        ]);
        $this->assertSame(100000.0, (float) $unit->fresh()->balance);

        $receipt = app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 200000], $loket->id);

        $this->assertSame(300000.0, (float) $receipt->total_billing);
        $this->assertSame(0.0, (float) $receipt->deposit_amount);
        $this->assertSame(100000.0, (float) $receipt->balance_used);
        $this->assertSame(0.0, (float) $unit->fresh()->balance);
        $this->assertSame(Billing::STATUS_PAID, Billing::where('unit_id', $unit->id)->firstOrFail()->status_id);
    }

    public function test_payment_spans_multiple_bills_fifo_with_partial_last(): void
    {
        $unit = $this->makeUnit();
        $first = $this->makeBilling($unit, 300000, -2);
        $second = $this->makeBilling($unit, 300000, -1);
        $third = $this->makeBilling($unit, 300000, 0);
        $loket = User::where('username', 'loket')->firstOrFail();

        app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 700000], $loket->id);

        $this->assertSame(Billing::STATUS_PAID, $first->fresh()->status_id);
        $this->assertSame(Billing::STATUS_PAID, $second->fresh()->status_id);
        $this->assertSame(Billing::STATUS_PARTIAL, $third->fresh()->status_id);
        $this->assertSame(100000.0, (float) $third->fresh()->principal_paid);
        $this->assertSame(0.0, (float) $unit->fresh()->balance);
    }

    public function test_payment_reversal_restores_billing_and_balance(): void
    {
        $unit = $this->makeUnit();
        $this->makeBilling($unit, 900000);
        $loket = User::where('username', 'loket')->firstOrFail();

        $receipt = app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 1000000], $loket->id);
        $this->assertSame(100000.0, (float) $unit->fresh()->balance);

        $reversal = Reversal::query()->create([
            'receipt_number' => $receipt->number,
            'reason' => 'Kesalahan input kasir.',
            'submitted_by' => $loket->id,
            'submitted_at' => now(),
        ])->fresh();
        app(ReversalService::class)->approve($reversal, $loket->id);

        $billing = Billing::where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame(Billing::STATUS_UNPAID, $billing->status_id);
        $this->assertSame(0.0, (float) $billing->principal_paid);
        $this->assertSame(0.0, (float) $unit->fresh()->balance);
        $this->assertSame('reversed', PaymentTransaction::findOrFail($receipt->payment_transaction_id)->status);
        $this->assertSame('cancelled', $receipt->fresh()->status);
        $this->assertDatabaseHas('unit_deposits', [
            'unit_id' => $unit->id,
            'type' => UnitDeposit::TYPE_REVERSAL,
            'direction' => 'debit',
            'amount' => 100000,
        ]);
    }

    public function test_reconciliation_detects_mismatch(): void
    {
        $unit = $this->makeUnit();
        $loket = User::where('username', 'loket')->firstOrFail();
        $ledgerService = app(UnitBalanceLedgerService::class);

        $ledgerService->credit($unit, UnitDeposit::TYPE_MANUAL_CREDIT, 500000, ['notes' => 'kredit', 'created_by' => $loket->id]);
        $ledgerService->debit($unit, UnitDeposit::TYPE_MANUAL_DEBIT, 200000, ['notes' => 'debit', 'created_by' => $loket->id]);

        $balanced = $ledgerService->reconcile($unit);
        $this->assertSame('balanced', $balanced['status']);
        $this->assertSame(300000.0, $balanced['calculated_balance']);
        $this->assertSame(300000.0, $balanced['stored_balance']);
        $this->assertSame(0.0, $balanced['difference']);

        // Simulate drift: something outside the ledger service touches the cached column
        // directly (a manual DB edit, a bug elsewhere) - reconciliation must surface this,
        // never silently re-sync it.
        DB::table('units')->where('id', $unit->id)->update(['balance' => 350000]);

        $mismatch = $ledgerService->reconcile($unit->fresh());
        $this->assertSame('mismatch', $mismatch['status']);
        $this->assertSame(300000.0, $mismatch['calculated_balance']);
        $this->assertSame(350000.0, $mismatch['stored_balance']);
        $this->assertSame(50000.0, $mismatch['difference']);
    }
}
