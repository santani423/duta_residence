<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClusterRateScheduleService;
use App\Services\CollectionAccountRefreshQueue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Tagihan IPL dari buku IPL manual 2024 sampai data terbaru (lihat IplData):
     *  - setiap bulan yang tercatat dibayar menjadi tagihan Lunas senilai uang yang dibayar
     *    (bulan yang dibayar lebih dari sekali - kekurangan/penambahan tagihan - digabung);
     *  - unit aktif di cluster yang ditagih lewat buku itu mendapat tagihan Belum Bayar untuk
     *    setiap bulan sejak Januari 2024 sampai bulan berjalan yang belum tercatat dibayar.
     *
     * Kuitansi/transaksi pembayarannya dibuat PaymentSeeder dengan nomor kuitansi yang sama.
     */
    public function run(): void
    {
        $finance = User::where('username', 'finance')->first() ?: User::where('username', 'root')->first();
        $loket = User::where('username', 'loket')->first() ?: $finance;
        $rateScheduleService = app(ClusterRateScheduleService::class);
        $rateCache = [];
        $clusterRate = function (Unit $unit, string $period) use (&$rateCache, $rateScheduleService): float {
            [$year, $month] = array_map('intval', explode('-', $period));

            return $rateCache["{$unit->cluster_id}|{$period}"] ??= $rateScheduleService->rateForPeriod($unit->cluster, $year, $month);
        };

        $units = Unit::with('cluster')->get()->keyBy('id');
        $now = now();
        $currentPeriod = $now->format('Y-m');
        $rows = [];

        foreach (IplData::payments() as $payment) {
            $unit = $units->get($payment['unit_id']);
            if (! $unit) {
                continue;
            }

            foreach ($payment['shares'] as $period => $share) {
                $key = "{$unit->id}|{$period}";

                if (isset($rows[$key])) {
                    // Sama seperti PaymentService: receipt_number = kuitansi terakhir yang menyentuh tagihan.
                    $rows[$key]['amount'] += $share;
                    $rows[$key]['principal_paid'] += $share;
                    $rows[$key]['paid_at'] = $payment['paid_at'];
                    $rows[$key]['receipt_number'] = $payment['receipt_number'];

                    continue;
                }

                $periodStart = CarbonImmutable::parse("{$period}-01");
                $paidAt = CarbonImmutable::parse($payment['paid_at']);

                $rows[$key] = $this->row($unit, $period, $share, $now, $finance) + [
                    'principal_paid' => $share,
                    'status_id' => Billing::STATUS_PAID,
                    // Pembayaran dimuka: tagihannya dianggap sudah diterbitkan saat dibayar.
                    'approved_at' => $periodStart->min($paidAt),
                    'approval_notes' => 'Impor buku IPL manual.',
                    'paid_at' => $payment['paid_at'],
                    'receipt_number' => $payment['receipt_number'],
                    'loket_code' => 'L01',
                    'processed_by' => $loket?->id,
                ];
            }
        }

        // Tarif riil bisa berbeda dari tarif cluster (mis. Alexandrite: AX3 Rp200rb, AX7 Rp350rb),
        // jadi tagihan Belum Bayar memakai tarif yang paling sering dibayar unit itu sejak 2024 -
        // atau tarif umum di bloknya bila unitnya belum pernah bayar - disetarakan ke tarif
        // cluster saat ini supaya kenaikan tarif cluster tetap ikut. Pembagian pembayaran borongan
        // yang tidak bulat (bukan kelipatan Rp2.500) tidak dipakai sebagai acuan.
        $paidByUnit = [];
        $unitRates = [];
        $blockRates = [];
        foreach ($rows as $row) {
            $unit = $units->get($row['unit_id']);
            $period = sprintf('%04d-%02d', $row['year'], $row['month']);
            $paidByUnit[$unit->id][] = $row;

            $rate = round($row['amount'] * $clusterRate($unit, $currentPeriod) / max(1, $clusterRate($unit, $period)));
            if ($period >= IplData::START_PERIOD && $rate > 0 && fmod($rate, 2500) == 0) {
                $unitRates[$unit->id][] = $rate;
                $blockRates[$unit->block][] = $rate;
            }
        }

        foreach ($units as $unit) {
            if ($unit->status_id !== 'AK' || ! in_array($unit->cluster_id, IplData::BILLED_CLUSTERS, true)) {
                continue;
            }

            $paid = $paidByUnit[$unit->id] ?? [];
            $unitRate = $this->mostFrequent($unitRates[$unit->id] ?? []) ?? $this->mostFrequent($blockRates[$unit->block] ?? []);
            $currentClusterRate = $clusterRate($unit, $currentPeriod);
            $rate = fn (string $period) => $unitRate && $currentClusterRate > 0
                ? round($unitRate * $clusterRate($unit, $period) / $currentClusterRate, -2)
                : $clusterRate($unit, $period);

            for ($period = $this->firstBilledPeriod($unit, $paid); $period <= $currentPeriod; $period = CarbonImmutable::parse("{$period}-01")->addMonth()->format('Y-m')) {
                $key = "{$unit->id}|{$period}";
                if (isset($rows[$key])) {
                    continue;
                }

                $rows[$key] = $this->row($unit, $period, $rate($period), $now, $finance) + [
                    'principal_paid' => 0,
                    'status_id' => Billing::STATUS_UNPAID,
                    'approved_at' => CarbonImmutable::parse("{$period}-01"),
                    'approval_notes' => $period === $currentPeriod ? 'Tagihan bulan berjalan.' : 'Menunggak - belum tercatat di buku IPL manual.',
                    'paid_at' => null,
                    'receipt_number' => null,
                    'loket_code' => null,
                    'processed_by' => null,
                ];
            }
        }

        // Ditulis bulk karena jumlahnya puluhan ribu tagihan dan seeder dijalankan ke database
        // remote. upsert tidak memicu observer, jadi cache collection account di-refresh lewat
        // antrean yang sama dengan BillingCollectionObserver.
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            Billing::upsert($chunk, ['unit_id', 'year', 'month'], array_diff(array_keys($chunk[0]), ['unit_id', 'year', 'month', 'created_at']));
        }

        $refreshQueue = app(CollectionAccountRefreshQueue::class);
        foreach (array_unique(array_column($rows, 'unit_id')) as $unitId) {
            $refreshQueue->push($unitId);
        }

        $this->ensureLegacyCustomerHasUnpaidBilling($finance);

        if ($this->command) {
            foreach (IplData::skipped() as $summary) {
                $this->command->warn(IplData::SOURCE." tidak diimpor - {$summary}");
            }
        }
    }

    private function row(Unit $unit, string $period, float $amount, $now, ?User $finance): array
    {
        [$year, $month] = array_map('intval', explode('-', $period));

        return [
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'amount' => $amount,
            'penalty' => 0,
            'penalty_paid' => 0,
            'discount' => 0,
            'discount_rule_id' => null,
            'is_penalty_eligible' => $unit->is_penalty_eligible,
            'is_discount_eligible' => $unit->is_discount_eligible,
            'billing_type' => 'regular',
            'approved_by' => $finance?->id,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'created_by' => $finance?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Tagihan mulai Januari 2024, atau sejak serah terima bila lebih baru. Unit tanpa tanggal
     * serah terima mulai dari periode pertama yang pernah dibayarnya, supaya unit baru tidak
     * terlihat menunggak sebelum mulai ditagih.
     */
    private function firstBilledPeriod(Unit $unit, array $paid): string
    {
        $start = match (true) {
            $unit->handover_date !== null => $unit->handover_date->format('Y-m'),
            $paid !== [] => min(array_map(fn (array $row) => sprintf('%04d-%02d', $row['year'], $row['month']), $paid)),
            default => IplData::START_PERIOD,
        };

        return max(IplData::START_PERIOD, $start);
    }

    /** Nilai yang paling sering muncul; bila seri, yang muncul terakhir (pembayaran terbaru). */
    private function mostFrequent(array $values): ?float
    {
        $counts = [];
        foreach ($values as $value) {
            $count = ($counts[(string) $value] ?? 0) + 1;
            unset($counts[(string) $value]);
            $counts[(string) $value] = $count;
        }

        return $counts === [] ? null : (float) array_key_last(array_filter($counts, fn (int $count) => $count === max($counts)));
    }

    /**
     * PaymentFlowTest dan test loket lain butuh unit 07012 punya tagihan Belum Bayar yang sudah
     * di-approve. Unit ini tercatat bayar sampai April 2026 di buku IPL sehingga sudah menunggak -
     * ini hanya jaring pengaman bila data IPL berubah.
     */
    private function ensureLegacyCustomerHasUnpaidBilling(?User $finance): void
    {
        $unit = Unit::with('cluster')->find(UnitSeeder::LEGACY_CUSTOMER_UNIT);
        $hasUnpaid = Billing::where('unit_id', UnitSeeder::LEGACY_CUSTOMER_UNIT)
            ->where('status_id', Billing::STATUS_UNPAID)->whereNotNull('approved_at')->exists();

        if (! $unit || $hasUnpaid) {
            return;
        }

        $period = now();
        Billing::firstOrCreate(
            ['unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month],
            [
                'amount' => (float) $unit->cluster->monthly_rate,
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
