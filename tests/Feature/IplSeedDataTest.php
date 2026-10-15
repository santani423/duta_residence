<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\ClusterRateSchedule;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data awal IPL dari docs/sempelIpl.md (buku IPL manual yang sudah dirapikan). Unit 09005 = blok
 * C1/6 (Jhoni Candra): bayar Feb, Apr-Jul, Sep, Nov 2025 dan Jan, Feb, Apr, Sep 2026, satu bulan
 * per kuitansi. Unit 07012 tidak punya pembayaran di file itu.
 */
class IplSeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_billings_and_receipts_follow_the_manual_ipl_book(): void
    {
        $this->seed();

        $billings = Billing::where('unit_id', '09005')->get()
            ->keyBy(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month));

        $this->assertSame(Billing::STATUS_PAID, $billings['2025-02']->status_id);
        $this->assertEquals(225000, $billings['2025-02']->amount);
        $this->assertSame(Billing::STATUS_UNPAID, $billings['2025-03']->status_id);
        $this->assertSame(Billing::STATUS_PAID, $billings['2026-09']->status_id);
        $this->assertEquals(250000, $billings['2026-09']->amount);

        $receipt = Receipt::where('number', $billings['2026-09']->receipt_number)->firstOrFail();
        $this->assertSame('2026-09-25', $receipt->transaction_date->toDateString());
        $this->assertEquals(250000, $receipt->grand_total);
        $this->assertSame(1, (int) $receipt->billing_count);
        $this->assertSame(['D', 'M'], [$receipt->payment_method_id, $receipt->payment_channel_id]);
        $this->assertStringContainsString('02375', $receipt->notes);
        $this->assertEquals(250000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));

        // Hanya transaksi di sempelIpl.md yang diimpor (22 baris lain bloknya tidak ada di pelanggan.csv).
        $this->assertSame(1725, Receipt::where('notes', 'like', 'Impor buku IPL manual.%')->count());
        $this->assertFalse(Billing::where('unit_id', '07012')->where('status_id', Billing::STATUS_PAID)->exists());
        $this->assertSame(Billing::STATUS_UNPAID, Billing::where('unit_id', '07012')->where('year', 2024)->where('month', 1)->value('status_id'));

        // Cluster tanpa pembayaran di buku IPL 2024+ tidak dibuatkan tunggakan.
        $this->assertFalse(Billing::whereHas('unit', fn ($q) => $q->where('cluster_id', '01'))->where('status_id', Billing::STATUS_UNPAID)->exists());

        $this->assertEquals(250000, ClusterRateSchedule::where('cluster_id', '09')->whereDate('effective_date', '2025-11-01')->value('rate'));
    }
}
