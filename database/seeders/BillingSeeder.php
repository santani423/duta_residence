<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Unit;
use App\Models\User;
use App\Services\PenaltyService;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Kedalaman histori tagihan maksimum per unit (bulan) - dipangkas otomatis untuk unit
     * yang serah terimanya belum selama itu (lihat historyMonthsFor()).
     */
    private const MAX_HISTORY_MONTHS = 24;

    /**
     * Unit contoh dengan umur tunggakan tertua yang tetap (bukan hasil dadu acak), supaya
     * mudah ditelusuri satu per satu saat demo/testing: dari baru menunggak sampai lebih
     * setahun. Nilainya adalah umur tunggakan (overdue_months) yang diinginkan untuk tagihan
     * TERTUA yang masih belum lunas - lihat konversi ke jumlah bulan di arrearsMonthsFor().
     */
    private const SHOWCASE_OLDEST_OVERDUE_MONTHS = [
        'GA001' => 1,
        'GA002' => 2,
        'GA003' => 3,
        'GA004' => 6,
        'GA005' => 12,
        'GA006' => 20,
    ];

    public function run(): void
    {
        $finance = User::where('username', 'finance')->first() ?: User::where('username', 'root')->first();
        $penaltyService = app(PenaltyService::class);
        $types = ['regular', 'security', 'cleaning', 'water', 'common-electricity', 'parking', 'maintenance', 'facility', 'special'];
        $skipUnits = ['AL005'];

        Unit::with('cluster')
            ->where('status_id', '!=', 'RK')
            ->orderBy('id')
            ->chunk(100, function ($units) use ($finance, $penaltyService, $types, $skipUnits) {
                foreach ($units as $unit) {
                    if (in_array($unit->id, $skipUnits, true)) {
                        continue;
                    }

                    $historyMonths = $this->historyMonthsFor($unit);
                    $arrears = min($this->arrearsMonthsFor($unit), $historyMonths);
                    // Bulan tertua dalam masa tunggakan kadang baru dicicil sebagian, bukan
                    // dibiarkan nol sama sekali - meniru penghuni yang mulai mencicil tunggakan lama.
                    $partialOffset = ($arrears >= 2 && crc32($unit->id.'-partial') % 10 < 3) ? $arrears - 1 : null;

                    for ($offset = $historyMonths - 1; $offset >= 0; $offset--) {
                        $period = now()->subMonths($offset);
                        $amount = (float) $unit->cluster->monthly_rate + (($offset % 2) * 25000);
                        $discount = $unit->is_discount_eligible ? 15000 : 0;
                        $approvedAt = now()->subMonths($offset)->subDays(6);
                        $notes = 'Approved dari demo seeder.';
                        $cancelledAt = null;
                        $cancellationReason = null;
                        $paidAt = null;
                        $principalPaid = 0;
                        $penaltyPaid = 0;
                        $penalty = 0;

                        $isWithinArrears = $offset < $arrears;
                        $status = $isWithinArrears
                            ? ($offset === $partialOffset ? Billing::STATUS_PARTIAL : Billing::STATUS_UNPAID)
                            : Billing::STATUS_PAID;

                        if ($status === Billing::STATUS_PAID) {
                            $paidAt = now()->subMonths($offset)->addDays(2);
                            $principalPaid = $amount - $discount;

                            // Hitung denda seolah tagihan masih outstanding pada tanggal pembayaran,
                            // lalu bekukan hasilnya - meniru urutan yang dipakai PaymentService::process().
                            $calcBilling = (new Billing([
                                'unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month,
                                'amount' => $amount, 'discount' => $discount, 'is_penalty_eligible' => $unit->is_penalty_eligible,
                                'status_id' => Billing::STATUS_UNPAID,
                            ]))->setRelation('unit', $unit);
                            $penalty = $penaltyService->calculatePenalty($calcBilling, $paidAt);
                            $penaltyPaid = $penalty;
                        } elseif ($status === Billing::STATUS_PARTIAL) {
                            $principalPaid = round($amount * (mt_rand(30, 70) / 100), 2);
                            $notes = 'Dibayar sebagian - sisa pokok dan denda masih tertunggak.';
                        } else {
                            $notes = $offset === 0 ? 'Tagihan bulan berjalan.' : 'Menunggak - belum ada pembayaran.';
                        }

                        // --- Skenario demo unit tertentu, override di atas hasil umum di atas ---
                        if ($unit->id === 'AL011' && $offset === 1) {
                            $status = Billing::STATUS_PARTIAL;
                            $principalPaid = round($amount / 2, 2);
                            $penaltyPaid = 0;
                            $penalty = 0;
                            $paidAt = null;
                            $notes = 'Dibayar sebagian - sisa pokok dan denda masih tertunggak.';
                        }

                        if ($unit->id === 'AL010' && $offset === 2) {
                            $status = Billing::STATUS_CANCELLED;
                            $cancelledAt = now()->subMonths($offset)->addDays(3);
                            $cancellationReason = 'Unit dalam renovasi, tagihan dibatalkan oleh admin estate.';
                            $notes = 'Dibatalkan untuk skenario demo.';
                            $paidAt = null;
                            $principalPaid = 0;
                            $penaltyPaid = 0;
                            $penalty = 0;
                        }

                        if ($unit->id === 'AL008' && $offset === 0) {
                            $approvedAt = null;
                            $status = Billing::STATUS_UNPAID;
                            $notes = 'Draft/pending approval untuk simulasi.';
                            $paidAt = null;
                            $principalPaid = 0;
                            $penaltyPaid = 0;
                            $penalty = 0;
                        }

                        Billing::updateOrCreate(
                            ['unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month],
                            [
                                'amount' => $amount,
                                'principal_paid' => $principalPaid,
                                'penalty' => $penalty,
                                'penalty_paid' => $penaltyPaid,
                                'discount' => $discount,
                                'status_id' => $status,
                                'is_penalty_eligible' => $unit->is_penalty_eligible,
                                'is_discount_eligible' => $unit->is_discount_eligible,
                                'billing_type' => $types[($offset + ord($unit->id[0])) % count($types)],
                                'approved_by' => $approvedAt ? $finance?->id : null,
                                'approved_at' => $approvedAt,
                                'approval_notes' => $notes,
                                'paid_at' => $paidAt,
                                'processed_by' => $paidAt ? $finance?->id : null,
                                'cancelled_at' => $cancelledAt,
                                'cancelled_by' => $cancelledAt ? $finance?->id : null,
                                'cancellation_reason' => $cancellationReason,
                                'created_by' => $finance?->id,
                            ]
                        );
                    }
                }
            });

        $ga = Unit::find('GA012');
        if ($ga) {
            $period = now();
            Billing::updateOrCreate(
                ['unit_id' => 'GA012', 'year' => $period->year, 'month' => $period->month],
                [
                    'amount' => (float) $ga->cluster->monthly_rate,
                    'penalty' => 0,
                    'discount' => 0,
                    'status_id' => Billing::STATUS_UNPAID,
                    'billing_type' => 'regular',
                    'approved_by' => $finance?->id,
                    'approved_at' => now()->subDays(5),
                    'approval_notes' => 'Tagihan wajib untuk PaymentFlowTest.',
                    'created_by' => $finance?->id,
                ]
            );
        }
    }

    /**
     * Jangan buat histori tagihan sebelum unit itu serah terima.
     */
    private function historyMonthsFor(Unit $unit): int
    {
        $monthsSinceHandover = $unit->handover_date
            ? (int) abs(now()->diffInMonths($unit->handover_date))
            : self::MAX_HISTORY_MONTHS;

        return max(1, min(self::MAX_HISTORY_MONTHS, $monthsSinceHandover));
    }

    /**
     * Sebaran umur tunggakan yang realistis dan deterministik (stabil setiap kali seeder
     * dijalankan ulang, berbasis hash id unit): ~40% unit lunas penuh (auto-debit/bayar cepat),
     * ~30% tagihan bulan berjalan saja (normal, bukan tunggakan), ~20% menunggak ringan
     * (1-3 bulan), ~7% menunggak sedang (4-11 bulan), ~3% menunggak berat (12-24 bulan).
     * Unit contoh (showcase) dan unit skenario khusus memakai nilai tetap, bukan hasil dadu.
     */
    private function arrearsMonthsFor(Unit $unit): int
    {
        if (isset(self::SHOWCASE_OLDEST_OVERDUE_MONTHS[$unit->id])) {
            // +1 karena tagihan tertua yang belum lunas ada di offset (arrears - 1): arrears
            // adalah JUMLAH tagihan yang belum dibayar (termasuk bulan berjalan di offset 0),
            // sedangkan overdue_months dihitung dari umur tagihan tertuanya.
            return self::SHOWCASE_OLDEST_OVERDUE_MONTHS[$unit->id] + 1;
        }

        if ($unit->id === 'AL001') {
            return 0; // "Andi Lunas Pratama" - seluruh tagihan lunas dan pembayaran lancar.
        }

        if ($unit->id === 'AL002') {
            return 5; // "Bima Menunggak Saputra" - tagihan jatuh tempo + denda tier 3 bulan+.
        }

        if ($unit->cluster_id === 'OR') {
            return 0; // Cluster premium, profil "pembayaran terbaik".
        }

        $roll = crc32($unit->id) % 100;

        // Nilai yang dikembalikan adalah JUMLAH tagihan belum lunas (arrears), bukan umur
        // tunggakan langsung - tagihan tertua ada di offset (arrears - 1), jadi umur
        // tunggakan tertuanya = arrears - 1. Rentang di bawah sudah memperhitungkan itu supaya
        // setiap tier benar-benar mencerminkan label komentarnya.
        return match (true) {
            $roll < 40 => 0,                                      // lunas penuh (auto-debit/bayar cepat)
            $roll < 70 => 1,                                      // hanya tagihan bulan berjalan (umur 0, bukan tunggakan)
            $roll < 90 => 2 + (crc32($unit->id.'-a') % 3),         // umur tunggakan tertua 1-3 bulan (ringan)
            $roll < 97 => 5 + (crc32($unit->id.'-b') % 8),         // umur tunggakan tertua 4-11 bulan (sedang)
            default => 13 + (crc32($unit->id.'-c') % 13),          // umur tunggakan tertua 12-24 bulan (berat)
        };
    }
}
