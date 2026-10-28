<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\ClusterRateSchedule;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data awal IPL dari buku IPL docs/final_revisi_1.md. Unit 09005 = blok C1/6 (Jhoni Candra):
 * bayar setiap bulan Jan 2024 - Sep 2026, satu bulan per kuitansi. Unit 07012 = AL2/15: bayar
 * per rentang sampai Apr 2026 (mis. Rp1.800.000 untuk "Nov-25 - Aprl-26" = 6 x Rp300.000).
 */
class IplSeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_billings_and_receipts_follow_the_manual_ipl_book(): void
    {
        $this->seed();

        $billings = Billing::where('unit_id', '09005')->get()
            ->keyBy(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month));

        // "Mart-25" yang dibayar 2025-04-08.
        $this->assertSame(Billing::STATUS_PAID, $billings['2025-03']->status_id);
        $this->assertEquals(225000, $billings['2025-03']->amount);
        $this->assertSame(Billing::STATUS_PAID, $billings['2026-09']->status_id);
        $this->assertEquals(250000, $billings['2026-09']->amount);
        $this->assertSame(Billing::STATUS_UNPAID, $billings['2026-10']->status_id);

        $receipt = Receipt::where('number', $billings['2026-09']->receipt_number)->firstOrFail();
        $this->assertSame('2026-09-25', $receipt->transaction_date->toDateString());
        $this->assertEquals(250000, $receipt->grand_total);
        $this->assertSame(1, (int) $receipt->billing_count);
        $this->assertSame(['D', 'M'], [$receipt->payment_method_id, $receipt->payment_channel_id]);
        $this->assertStringContainsString('2375 / KW.2026.09.0217', $receipt->notes);
        $this->assertEquals(250000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));

        // Satu kuitansi untuk beberapa bulan: nominal dibagi rata ke setiap bulan.
        $billing = Billing::where('unit_id', '07012')->where('year', 2026)->where('month', 1)->firstOrFail();
        $this->assertSame(Billing::STATUS_PAID, $billing->status_id);
        $this->assertEquals(300000, $billing->amount);
        $receipt = Receipt::where('number', $billing->receipt_number)->firstOrFail();
        $this->assertSame('2026-04-22', $receipt->transaction_date->toDateString());
        $this->assertEquals(1800000, $receipt->grand_total);
        $this->assertSame(6, (int) $receipt->billing_count);
        $this->assertSame(6, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->count());
        $this->assertEquals(1800000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));
        $this->assertSame(Billing::STATUS_UNPAID, Billing::where('unit_id', '07012')->where('year', 2026)->where('month', 5)->value('status_id'));

        // Hanya kuitansi yang membayar periode sejak 2024 yang diimpor.
        $this->assertSame(8383, Receipt::where('notes', 'like', 'Impor buku IPL manual.%')->count());

        // Cluster tanpa pembayaran di buku IPL 2024+ tidak dibuatkan tunggakan.
        $this->assertFalse(Billing::whereHas('unit', fn ($q) => $q->where('cluster_id', '01'))->where('status_id', Billing::STATUS_UNPAID)->exists());

        $this->assertEquals(250000, ClusterRateSchedule::where('cluster_id', '09')->whereDate('effective_date', '2025-11-01')->value('rate'));
    }
}
