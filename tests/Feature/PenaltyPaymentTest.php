<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\PaymentAllocation;
use App\Models\PenaltyRule;
use App\Models\Resident;
use App\Models\Reversal;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use App\Services\PenaltyWaiverService;
use App\Services\ReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PenaltyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Creates a fresh unit with 6 monthly invoices (bulan berjalan s.d. 5 bulan menunggak)
     * at a fixed principal, mirroring the exact scenario from the spec (Juli-Desember @ Rp500.000).
     */
    private function makeUnitWithArrears(float $amount = 500000): Unit
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create([
            'resident_id' => $resident->id,
            'is_penalty_eligible' => true,
        ]);
        $finance = User::where('username', 'finance')->firstOrFail();

        foreach (range(5, 0) as $offset) {
            $period = now()->startOfMonth()->subMonths($offset);
            Billing::query()->create([
                'unit_id' => $unit->id,
                'year' => $period->year,
                'month' => $period->month,
                'amount' => $amount,
                'status_id' => Billing::STATUS_UNPAID,
                'is_penalty_eligible' => true,
                'billing_type' => 'regular',
                'approved_by' => $finance->id,
                'approved_at' => $period->copy()->addDays(2),
                'created_by' => $finance->id,
            ]);
        }

        return $unit->fresh();
    }

    public function test_six_month_arrears_totals_match_specification_example(): void
    {
        $unit = $this->makeUnitWithArrears();
        $billingIds = Billing::where('unit_id', $unit->id)->pluck('id')->all();

        $preview = app(PaymentService::class)->preview($unit, $billingIds);

        $this->assertSame(3000000.0, $preview['total_billing']);
        $this->assertSame(120000.0, $preview['total_penalty']);
        $this->assertSame(3120000.0, $preview['grand_total']);
    }

    public function test_full_payment_settles_all_invoices_with_correct_allocations(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingIds = Billing::where('unit_id', $unit->id)->pluck('id')->all();

        $receipt = app(PaymentService::class)->process($unit, $billingIds, ['payment_method_id' => 'C'], $loket->id);

        $this->assertSame(3000000.0, (float) $receipt->total_billing);
        $this->assertSame(120000.0, (float) $receipt->total_penalty);
        $this->assertSame(3120000.0, (float) $receipt->grand_total);
        $this->assertSame(6, $receipt->billing_count);
        $this->assertSame(6, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->count());
        $this->assertSame(0, Billing::where('unit_id', $unit->id)->where('status_id', '!=', Billing::STATUS_PAID)->count());
    }

    public function test_partial_amount_settles_oldest_invoice_first(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billings = Billing::where('unit_id', $unit->id)->orderBy('year')->orderBy('month')->get();

        // Tagihan tertua (5 bulan menunggak): 500.000 pokok + 30.000 denda = 530.000
        $receipt = app(PaymentService::class)->process(
            $unit,
            $billings->pluck('id')->all(),
            ['payment_method_id' => 'C', 'amount' => 530000],
            $loket->id
        );

        $this->assertSame(Billing::STATUS_PAID, $billings->first()->fresh()->status_id);
        $this->assertSame(Billing::STATUS_UNPAID, $billings->skip(1)->first()->fresh()->status_id);
        $this->assertSame(1, $receipt->billing_count);
    }

    public function test_payment_cannot_exceed_total_outstanding(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingIds = Billing::where('unit_id', $unit->id)->pluck('id')->all();

        $this->expectException(ValidationException::class);
        app(PaymentService::class)->process($unit, $billingIds, ['payment_method_id' => 'C', 'amount' => 99999999], $loket->id);
    }

    public function test_penalty_rule_change_does_not_alter_historical_payment_snapshot(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $oldest = Billing::where('unit_id', $unit->id)->orderBy('year')->orderBy('month')->first();

        app(PaymentService::class)->process($unit, [$oldest->id], ['payment_method_id' => 'C'], $loket->id);

        $allocation = PaymentAllocation::where('billing_id', $oldest->id)->firstOrFail();
        $this->assertSame(30000.0, (float) $allocation->penalty_amount);

        PenaltyRule::query()->whereNull('cluster_id')->where('minimum_overdue_month', 3)->update(['penalty_amount' => 99999]);

        $this->assertSame(30000.0, (float) $oldest->fresh()->penalty);
        $this->assertSame(30000.0, (float) $allocation->fresh()->penalty_amount);
    }

    public function test_penalty_waiver_reduces_effective_penalty_and_logs_audit(): void
    {
        $unit = $this->makeUnitWithArrears();
        $billing = Billing::where('unit_id', $unit->id)->orderBy('year')->orderBy('month')->first();
        $finance = User::where('username', 'finance')->firstOrFail();

        $waiverService = app(PenaltyWaiverService::class);
        $waiver = $waiverService->submit($billing, 30000, 'Keringanan karena bencana alam.', $finance->id);
        $this->assertSame('pending', $waiver->status);

        $waiverService->approve($waiver, $finance->id);

        $this->assertSame(0.0, app(PenaltyService::class)->calculatePenalty($billing->fresh()));
        $this->assertDatabaseHas('audit_logs', ['module' => 'billings', 'action' => 'WAIVE_APPROVE']);
    }

    public function test_cancelled_billing_has_no_penalty_and_cannot_be_paid(): void
    {
        $unit = $this->makeUnitWithArrears();
        $billing = Billing::where('unit_id', $unit->id)->orderBy('year')->orderBy('month')->first();
        $billing->update([
            'status_id' => Billing::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Skenario pengujian.',
        ]);

        $this->assertSame(0.0, app(PenaltyService::class)->calculatePenalty($billing->fresh()));

        $loket = User::where('username', 'loket')->firstOrFail();
        $this->expectException(ValidationException::class);
        app(PaymentService::class)->process($unit, [$billing->id], ['payment_method_id' => 'C'], $loket->id);
    }

    public function test_reversal_restores_billings_using_allocation_amounts(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingIds = Billing::where('unit_id', $unit->id)->pluck('id')->all();

        $receipt = app(PaymentService::class)->process($unit, $billingIds, ['payment_method_id' => 'C'], $loket->id);

        $reversal = Reversal::query()->create([
            'receipt_number' => $receipt->number,
            'reason' => 'Kesalahan input kasir.',
            'submitted_by' => $loket->id,
            'submitted_at' => now(),
        ])->fresh();
        app(ReversalService::class)->approve($reversal, $loket->id);

        $this->assertSame(6, Billing::where('unit_id', $unit->id)->where('status_id', Billing::STATUS_UNPAID)->count());
        $this->assertSame('cancelled', $receipt->fresh()->status);
    }

    public function test_reversing_an_older_receipt_only_undoes_that_receipts_own_contribution(): void
    {
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $oldest = Billing::where('unit_id', $unit->id)->orderBy('year')->orderBy('month')->first();

        // Receipt A: bayar 500.000 dari total 530.000 (pokok 500.000 + denda 30.000).
        // Alokasi default (penalty_first) melunasi seluruh denda dulu (30.000), sisanya (470.000)
        // masuk ke pokok, menyisakan 30.000 pokok yang belum terbayar.
        $receiptA = app(PaymentService::class)->process($unit, [$oldest->id], ['payment_method_id' => 'C', 'amount' => 500000], $loket->id);
        $this->assertSame(Billing::STATUS_PARTIAL, $oldest->fresh()->status_id);

        // Receipt B: lunasi sisa 30.000 pokok yang tersisa.
        $receiptB = app(PaymentService::class)->process($unit, [$oldest->id], ['payment_method_id' => 'C'], $loket->id);
        $this->assertSame(Billing::STATUS_PAID, $oldest->fresh()->status_id);
        $this->assertSame($receiptB->number, $oldest->fresh()->receipt_number);

        // Membatalkan receipt A (yang lebih tua) tidak boleh menghapus kontribusi receipt B:
        // hanya 470.000 pokok + 30.000 denda (kontribusi A) yang dibalik, menyisakan 30.000
        // pokok (kontribusi B) tetap tercatat sebagai sudah dibayar.
        $reversalA = Reversal::query()->create([
            'receipt_number' => $receiptA->number,
            'reason' => 'Kesalahan input kasir pada transaksi pertama.',
            'submitted_by' => $loket->id,
            'submitted_at' => now(),
        ])->fresh();
        app(ReversalService::class)->approve($reversalA, $loket->id);

        $oldest->refresh();
        $this->assertSame(Billing::STATUS_PARTIAL, $oldest->status_id);
        $this->assertSame(30000.0, (float) $oldest->principal_paid);
        $this->assertSame(0.0, (float) $oldest->penalty_paid);
        // receipt_number masih menunjuk ke receipt B yang belum dibatalkan.
        $this->assertSame($receiptB->number, $oldest->receipt_number);
    }
}
