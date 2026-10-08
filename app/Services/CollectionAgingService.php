<?php

namespace App\Services;

use App\Models\CollectionAccountState;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bucket aging penagihan berbasis HARI sejak jatuh tempo tertua yang belum lunas.
 * Berbeda dari umur tunggakan berbasis BULAN di PenaltyService (acuan tier denda) - keduanya
 * sengaja dipisah: denda mengikuti aturan bulan, prioritas penagihan butuh resolusi hari.
 */
class CollectionAgingService
{
    public const BUCKET_CURRENT = 'current';

    public const BUCKET_1_30 = '1_30';

    public const BUCKET_31_60 = '31_60';

    public const BUCKET_61_90 = '61_90';

    public const BUCKET_91_180 = '91_180';

    public const BUCKET_180_PLUS = '180_plus';

    /** Urutan tampilan + label. */
    public const BUCKETS = [
        self::BUCKET_CURRENT => 'Current',
        self::BUCKET_1_30 => '1–30 Hari',
        self::BUCKET_31_60 => '31–60 Hari',
        self::BUCKET_61_90 => '61–90 Hari',
        self::BUCKET_91_180 => '91–180 Hari',
        self::BUCKET_180_PLUS => '>180 Hari',
    ];

    public function agingDays(?CarbonInterface $oldestDueDate, ?CarbonInterface $date = null): int
    {
        if ($oldestDueDate === null) {
            return 0;
        }

        $date = ($date ?? now())->copy()->startOfDay();
        $due = $oldestDueDate->copy()->startOfDay();

        return $due->lessThan($date) ? (int) $due->diffInDays($date) : 0;
    }

    public function bucketFor(int $agingDays): string
    {
        return match (true) {
            $agingDays <= 0 => self::BUCKET_CURRENT,
            $agingDays <= 30 => self::BUCKET_1_30,
            $agingDays <= 60 => self::BUCKET_31_60,
            $agingDays <= 90 => self::BUCKET_61_90,
            $agingDays <= 180 => self::BUCKET_91_180,
            default => self::BUCKET_180_PLUS,
        };
    }

    /**
     * Ringkasan per bucket (jumlah akun, outstanding, persentase outstanding) dari query
     * collection_account_states yang sudah di-scope pemanggil (collector/supervisor/filter).
     *
     * @param  Builder<CollectionAccountState>  $states
     * @return list<array{bucket: string, label: string, account_count: int, outstanding_amount: float, percentage: float}>
     */
    public function breakdown(Builder $states): array
    {
        $rows = (clone $states)
            ->reorder()
            ->where('outstanding_total', '>', 0)
            ->selectRaw('aging_bucket, COUNT(*) as account_count, SUM(outstanding_total) as outstanding_amount')
            ->groupBy('aging_bucket')
            ->get()
            ->keyBy('aging_bucket');

        $total = (float) $rows->sum('outstanding_amount');

        return collect(self::BUCKETS)->map(function (string $label, string $bucket) use ($rows, $total) {
            $amount = round((float) ($rows->get($bucket)?->outstanding_amount ?? 0), 2);

            return [
                'bucket' => $bucket,
                'label' => $label,
                'account_count' => (int) ($rows->get($bucket)?->account_count ?? 0),
                'outstanding_amount' => $amount,
                'percentage' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        })->values()->all();
    }
}
