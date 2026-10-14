<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\ClusterRateSchedule;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data awal IPL dari database/data/ipl.csv (buku IPL manual 2024+). Unit 07012 = blok AL2/15:
 * bayar "Des-22 - Sep 24" Rp5,5jt (tunai), "Okt-24 - Maret 25" Rp1,65jt, "Aprl-Okt 25" Rp2,1jt,
 * "Nov-25 - Aprl-26" Rp1,8jt, lalu menunggak sejak Mei 2026.
 */
class IplSeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_billings_and_receipts_follow_the_manual_ipl_book(): void
    {
        $this->seed();

        $billings = Billing::where('unit_id', '07012')->get()
            ->keyBy(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month));

        $this->assertSame(Billing::STATUS_PAID, $billings['2022-12']->status_id);
        $this->assertSame(Billing::STATUS_PAID, $billings['2024-09']->status_id);
        $this->assertEquals(250000, $billings['2024-09']->amount);
        // "Okt-24 - Maret 25" Rp1,65jt terbagi mengikuti kenaikan tarif Ruko Alexandrite 2025.
        $this->assertEquals(250000, $billings['2024-12']->amount);
        $this->assertEquals(300000, $billings['2025-01']->amount);
        $this->assertSame(Billing::STATUS_PAID, $billings['2026-04']->status_id);
        $this->assertSame(Billing::STATUS_UNPAID, $billings['2026-05']->status_id);
        $this->assertEquals(300000, $billings['2026-05']->amount);
        $this->assertSame(Billing::STATUS_UNPAID, $billings[now()->format('Y-m')]->status_id);

        $receipt = Receipt::where('number', $billings['2024-09']->receipt_number)->firstOrFail();
        $this->assertSame('2024-09-12', $receipt->transaction_date->toDateString());
        $this->assertEquals(5500000, $receipt->grand_total);
        $this->assertSame(22, (int) $receipt->billing_count);
        $this->assertSame(['C', 'L'], [$receipt->payment_method_id, $receipt->payment_channel_id]);
        $this->assertStringContainsString('002107', $receipt->notes);
        $this->assertEquals(5500000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));

        // Cluster tanpa pembayaran di buku IPL 2024+ tidak dibuatkan tunggakan.
        $this->assertFalse(Billing::whereHas('unit', fn ($q) => $q->where('cluster_id', '01'))->where('status_id', Billing::STATUS_UNPAID)->exists());

        $this->assertEquals(250000, ClusterRateSchedule::where('cluster_id', '09')->whereDate('effective_date', '2025-11-01')->value('rate'));
    }
}
