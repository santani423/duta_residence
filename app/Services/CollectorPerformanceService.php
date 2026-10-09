<?php

namespace App\Services;

use App\Models\CollectionAccountState;
use App\Models\CollectorAssignment;
use App\Models\CollectorTarget;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Performa collector per periode (harian/mingguan/bulanan).
 *
 * Aturan "tertagih" (§7 rancangan): payment_transactions berstatus `paid`, tanggal bayar
 * (`paid_at`, fallback `created_at`) dalam periode, dan diatribusikan ke collector bila
 * `collected_by` = collector ATAU (`collected_by` kosong, `payment_provider = loket`, dan
 * `created_by` = collector) — kompatibel dengan data loket lama sebelum kolom collected_by ada.
 *
 * Semua metrik dihitung batched (GROUP BY per sumber data) — tidak ada query per collector.
 */
class CollectorPerformanceService
{
    /** Metrik yang boleh dipakai untuk mengurutkan peringkat. */
    public const RANKING_METRICS = [
        'collected_amount', 'collection_rate', 'achievement_percent_raw', 'visit_count',
        'successful_visit_rate', 'ptp_fulfilled', 'ptp_fulfillment_rate',
    ];

    public const PERIOD_TYPES = [
        CollectorTarget::PERIOD_DAILY, CollectorTarget::PERIOD_WEEKLY, CollectorTarget::PERIOD_MONTHLY,
    ];

    /** Batas jumlah id per klausa IN agar aman untuk batas parameter driver. */
    private const ID_CHUNK = 500;

    /**
     * Normalisasi periode: harian = hari itu, mingguan = Senin s/d Minggu, bulanan = tanggal 1
     * s/d akhir bulan. `$date` kosong = periode berjalan. Tipe tak dikenal diperlakukan harian.
     *
     * @return array{type: string, start: CarbonImmutable, end: CarbonImmutable, start_date: string, end_date: string}
     */
    public function resolvePeriod(string $type, ?string $date): array
    {
        $type = in_array($type, self::PERIOD_TYPES, true) ? $type : CollectorTarget::PERIOD_DAILY;
        $base = ($date !== null && $date !== '' ? CarbonImmutable::parse($date) : CarbonImmutable::now())->startOfDay();

        [$start, $end] = match ($type) {
            CollectorTarget::PERIOD_WEEKLY => [
                $base->startOfWeek(CarbonInterface::MONDAY),
                $base->startOfWeek(CarbonInterface::MONDAY)->addDays(6)->endOfDay(),
            ],
            CollectorTarget::PERIOD_MONTHLY => [$base->startOfMonth(), $base->endOfMonth()],
            default => [$base, $base->endOfDay()],
        };

        return [
            'type' => $type,
            'start' => $start,
            'end' => $end,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    /** Bentuk periode untuk respons JSON: `{type, start, end}` (Y-m-d). */
    public function periodPayload(array $period): array
    {
        return [
            'type' => $period['type'],
            'start' => $period['start']->toDateString(),
            'end' => $period['end']->toDateString(),
        ];
    }

    /**
     * @deprecated Pakai resolvePeriod(). Dipertahankan untuk pemanggil lama.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodRange(string $periodType, string $periodStart): array
    {
        $period = $this->resolvePeriod($periodType, $periodStart);

        return [$period['start'], $period['end']];
    }

    /**
     * Shape lama (dipakai Flutter `collector-performance/me` & `supervisor/targets`) — key lama
     * tetap ada dengan arti sama (`achievement_percent` dibatasi 100, `target_amount` 0 bila
     * belum ada target), ditambah seluruh metrik baru.
     */
    public function achievementFor(User $collector, string $periodType, string $periodStart): array
    {
        $period = $this->resolvePeriod($periodType, $periodStart);
        $metrics = $this->metricsFor([(int) $collector->id], $period)[(int) $collector->id];

        return $this->achievementFromMetrics($metrics, $period);
    }

    /** Ubah satu baris metricsFor() menjadi shape achievement lama (+ metrik baru). */
    public function achievementFromMetrics(array $metrics, array $period): array
    {
        return [
            ...$metrics,
            'period_type' => $period['type'],
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'target_amount' => (float) ($metrics['target_amount'] ?? 0),
            'collected_amount' => $metrics['collected_amount'],
            'achievement_percent' => $metrics['achievement_percent'],
            'target_visit_count' => $metrics['target_visit_count'],
            'visit_count' => $metrics['visit_count'],
        ];
    }

    /**
     * Target periode untuk collector-collector tersebut, di-key collector_id.
     *
     * @param  list<int>  $collectorIds
     * @return Collection<int, CollectorTarget>
     */
    public function targetsFor(array $collectorIds, array $period): Collection
    {
        $collectorIds = $this->normalizeIds($collectorIds);
        if (! $collectorIds) {
            return collect();
        }

        return collect(array_chunk($collectorIds, self::ID_CHUNK))
            ->flatMap(fn (array $chunk) => CollectorTarget::query()
                ->whereIn('collector_id', $chunk)
                ->where('period_type', $period['type'])
                ->whereDate('period_start', $period['start']->toDateString())
                ->get())
            ->keyBy(fn (CollectorTarget $target) => (int) $target->collector_id);
    }

    /**
     * Metrik performa batched untuk banyak collector sekaligus.
     *
     * Catatan: `collection_rate` = tertagih ÷ (tertagih + tunggakan SAAT INI) × 100 — aproksimasi
     * sampai snapshot tunggakan per periode tersedia (T12). `overdue_reduction` selalu null karena
     * butuh snapshot yang sama.
     *
     * @param  list<int>  $collectorIds
     * @return array<int, array<string, mixed>> di-key collector id; setiap id input selalu ada.
     */
    public function metricsFor(array $collectorIds, array $period): array
    {
        $collectorIds = $this->normalizeIds($collectorIds);
        if (! $collectorIds) {
            return [];
        }

        $payments = [];
        $visits = [];
        $promisesCreated = [];
        $promisesResolved = [];
        $states = [];

        foreach (array_chunk($collectorIds, self::ID_CHUNK) as $chunk) {
            $payments += $this->paymentAggregates($chunk, $period);
            $visits += $this->visitAggregates($chunk, $period);
            $promisesCreated += $this->promiseCreatedAggregates($chunk, $period);
            $promisesResolved += $this->promiseResolvedAggregates($chunk, $period);
            $states += $this->stateAggregates($chunk);
        }

        $targets = $this->targetsFor($collectorIds, $period);

        $result = [];
        foreach ($collectorIds as $id) {
            $collected = round((float) ($payments[$id]->collected_amount ?? 0), 2);
            $visitCount = (int) ($visits[$id]->visit_count ?? 0);
            $successfulVisits = (int) ($visits[$id]->successful_visit_count ?? 0);
            $ptpFulfilled = (int) ($promisesResolved[$id]->ptp_fulfilled ?? 0);
            $ptpBroken = (int) ($promisesResolved[$id]->ptp_broken ?? 0);
            $outstanding = round((float) ($states[$id]->outstanding_total ?? 0), 2);

            /** @var CollectorTarget|null $target */
            $target = $targets->get($id);
            $targetAmount = $target ? (float) $target->target_amount : null;
            $targetVisits = $target?->target_visit_count !== null ? (int) $target->target_visit_count : null;
            $targetAccounts = $target?->target_account_count !== null ? (int) $target->target_account_count : null;
            $targetRate = $target?->target_collection_rate !== null ? (float) $target->target_collection_rate : null;
            $assignedAccounts = (int) ($states[$id]->assigned_accounts ?? 0);

            $achievementRaw = $targetAmount > 0 ? round($collected / $targetAmount * 100, 1) : null;

            $result[$id] = [
                'collector_id' => $id,
                'collected_amount' => $collected,
                'payment_count' => (int) ($payments[$id]->payment_count ?? 0),
                'visit_count' => $visitCount,
                'successful_visit_count' => $successfulVisits,
                'successful_visit_rate' => $this->percent($successfulVisits, $visitCount),
                'ptp_created' => (int) ($promisesCreated[$id]->ptp_created ?? 0),
                'ptp_fulfilled' => $ptpFulfilled,
                'ptp_broken' => $ptpBroken,
                'ptp_fulfillment_rate' => $this->percent($ptpFulfilled, $ptpFulfilled + $ptpBroken),
                'assigned_accounts' => $assignedAccounts,
                'outstanding_total' => $outstanding,
                'overdue_accounts' => (int) ($states[$id]->overdue_accounts ?? 0),
                'critical_accounts' => (int) ($states[$id]->critical_accounts ?? 0),
                'collection_rate' => $this->percent($collected, $collected + $outstanding),
                'overdue_reduction' => null,
                'target_id' => $target?->id,
                'target_amount' => $targetAmount,
                'target_visit_count' => $targetVisits,
                'target_account_count' => $targetAccounts,
                'target_collection_rate' => $targetRate,
                'achievement_percent_raw' => $achievementRaw,
                'achievement_percent' => $achievementRaw === null ? null : min($achievementRaw, 100.0),
                'visit_achievement_percent' => $targetVisits > 0 ? round($visitCount / $targetVisits * 100, 1) : null,
                'account_achievement_percent' => $targetAccounts > 0 ? round($assignedAccounts / $targetAccounts * 100, 1) : null,
            ];
        }

        return $result;
    }

    /**
     * Peringkat collector berdasarkan satu metrik (desc; nilai null di akhir; seri → nama).
     *
     * @param  list<int>  $collectorIds
     * @return list<array<string, mixed>>
     */
    public function ranking(array $collectorIds, array $period, string $metric): array
    {
        $metric = in_array($metric, self::RANKING_METRICS, true) ? $metric : 'collected_amount';
        $metrics = $this->metricsFor($collectorIds, $period);
        if (! $metrics) {
            return [];
        }

        $collectors = User::query()
            ->withTrashed()
            ->whereIn('id', array_keys($metrics))
            ->with('collectorProfile:id,user_id,collector_code,account_status')
            ->get(['id', 'name', 'is_active'])
            ->keyBy('id');

        $rows = collect($metrics)
            ->map(fn (array $row, int $id) => [
                'collector' => $this->collectorSummary($collectors->get($id), $id),
                ...$row,
            ])
            ->values()
            ->all();

        usort($rows, function (array $a, array $b) use ($metric) {
            $va = $a[$metric];
            $vb = $b[$metric];
            if ($va === null || $vb === null) {
                if ($va !== $vb) {
                    return $va === null ? 1 : -1;
                }
            } elseif ($va != $vb) {
                return $vb <=> $va;
            }

            return strcasecmp((string) $a['collector']['name'], (string) $b['collector']['name']);
        });

        foreach ($rows as $index => &$row) {
            $row = ['rank' => $index + 1, ...$row];
        }
        unset($row);

        return $rows;
    }

    /** `{id, name, collector_code, account_status, is_active}` untuk respons progres/peringkat. */
    public function collectorSummary(?User $collector, ?int $fallbackId = null): array
    {
        return [
            'id' => $collector?->id ?? $fallbackId,
            'name' => $collector?->name,
            'collector_code' => $collector?->collectorProfile?->collector_code,
            'account_status' => $collector?->collectorProfile?->account_status,
            'is_active' => $collector ? (bool) $collector->is_active : null,
        ];
    }

    /**
     * Batasi query collector ke yang assignment aktif & berlakunya menyentuh cluster tersebut
     * (scope cluster/block, unit di cluster, atau resident pemilik unit di cluster).
     */
    public function constrainCollectorsToCluster(EloquentBuilder|QueryBuilder $query, string $clusterId, string $column = 'users.id'): EloquentBuilder|QueryBuilder
    {
        $unitIds = Unit::query()->where('cluster_id', $clusterId)->select('id');
        $residentIds = Unit::query()->where('cluster_id', $clusterId)->whereNotNull('resident_id')->select('resident_id');

        return $query->whereIn($column, CollectorAssignment::query()
            ->active()
            ->currentlyEffective()
            ->where(fn (EloquentBuilder $q) => $q->where('cluster_id', $clusterId)
                ->orWhereIn('unit_id', $unitIds)
                ->orWhereIn('resident_id', $residentIds))
            ->select('collector_id'));
    }

    /** @return array<int, object{collected_amount: mixed, payment_count: mixed}> */
    private function paymentAggregates(array $ids, array $period): array
    {
        $collectorExpr = "CASE WHEN collected_by IS NOT NULL THEN collected_by WHEN payment_provider = 'loket' THEN created_by END";

        return PaymentTransaction::query()
            ->toBase()
            ->selectRaw("{$collectorExpr} as collector_id, SUM(total) as collected_amount, COUNT(*) as payment_count")
            ->where('status', 'paid')
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', $this->bounds($period))
            ->where(fn (QueryBuilder|EloquentBuilder $q) => $q->whereIn('collected_by', $ids)
                ->orWhere(fn ($legacy) => $legacy->whereNull('collected_by')
                    ->where('payment_provider', 'loket')
                    ->whereIn('created_by', $ids)))
            ->groupByRaw($collectorExpr)
            ->get()
            ->keyBy(fn ($row) => (int) $row->collector_id)
            ->all();
    }

    /** @return array<int, object{visit_count: mixed, successful_visit_count: mixed}> */
    private function visitAggregates(array $ids, array $period): array
    {
        $failedStatuses = $this->quotedList(CollectorVisit::FAILED_STATUSES);
        $failedCodes = $this->quotedList(CollectorVisit::FAILED_RESULT_CODES);
        $lifecycleFailed = CollectorVisit::LIFECYCLE_FAILED;
        $successExpr = "CASE WHEN (status IS NULL OR status NOT IN ({$failedStatuses}))"
            ." AND (result_code IS NULL OR result_code NOT IN ({$failedCodes}))"
            ." AND (lifecycle IS NULL OR lifecycle <> '{$lifecycleFailed}') THEN 1 ELSE 0 END";

        return CollectorVisit::query()
            ->selectRaw("collector_id, COUNT(*) as visit_count, SUM({$successExpr}) as successful_visit_count")
            ->whereIn('collector_id', $ids)
            ->whereBetween('visit_date', $this->bounds($period))
            ->where(fn (EloquentBuilder $q) => $q->whereNull('lifecycle')
                ->orWhereIn('lifecycle', [CollectorVisit::LIFECYCLE_COMPLETED, CollectorVisit::LIFECYCLE_FAILED]))
            ->groupBy('collector_id')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => (int) $row->collector_id)
            ->all();
    }

    /** PTP dibuat dalam periode, diatribusikan ke collector_id (fallback created_by). */
    private function promiseCreatedAggregates(array $ids, array $period): array
    {
        return PaymentPromise::query()
            ->selectRaw('COALESCE(collector_id, created_by) as attributed_collector_id, COUNT(*) as ptp_created')
            ->whereBetween('created_at', $this->bounds($period))
            ->where(fn (EloquentBuilder $q) => $this->whereAttributedPromise($q, $ids))
            ->groupByRaw('COALESCE(collector_id, created_by)')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => (int) $row->attributed_collector_id)
            ->all();
    }

    /** PTP yang terpenuhi / ingkar dalam periode (fulfilled_at / broken_at, fallback updated_at). */
    private function promiseResolvedAggregates(array $ids, array $period): array
    {
        [$from, $to] = $this->bounds($period);
        $fulfilled = PaymentPromise::STATUS_FULFILLED;
        $broken = PaymentPromise::STATUS_BROKEN;

        return PaymentPromise::query()
            ->selectRaw(
                'COALESCE(collector_id, created_by) as attributed_collector_id,'
                ." SUM(CASE WHEN status = '{$fulfilled}' AND COALESCE(fulfilled_at, updated_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) as ptp_fulfilled,"
                ." SUM(CASE WHEN status = '{$broken}' AND COALESCE(broken_at, updated_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) as ptp_broken",
                [$from, $to, $from, $to],
            )
            ->whereIn('status', [$fulfilled, $broken])
            ->where(fn (EloquentBuilder $q) => $q
                ->where(fn ($f) => $f->where('status', $fulfilled)->whereRaw('COALESCE(fulfilled_at, updated_at) BETWEEN ? AND ?', [$from, $to]))
                ->orWhere(fn ($b) => $b->where('status', $broken)->whereRaw('COALESCE(broken_at, updated_at) BETWEEN ? AND ?', [$from, $to])))
            ->where(fn (EloquentBuilder $q) => $this->whereAttributedPromise($q, $ids))
            ->groupByRaw('COALESCE(collector_id, created_by)')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => (int) $row->attributed_collector_id)
            ->all();
    }

    /** Akun yang saat ini dipegang collector (cache collection_account_states, unit aktif). */
    private function stateAggregates(array $ids): array
    {
        $critical = CollectionAccountState::PRIORITY_CRITICAL;

        return CollectionAccountState::query()
            ->join('units', 'units.id', '=', 'collection_account_states.unit_id')
            ->whereNull('units.deleted_at')
            ->whereIn('collection_account_states.collector_id', $ids)
            ->selectRaw(
                'collection_account_states.collector_id as collector_id,'
                .' COUNT(*) as assigned_accounts,'
                .' SUM(collection_account_states.outstanding_total) as outstanding_total,'
                .' SUM(CASE WHEN collection_account_states.aging_days > 0 AND collection_account_states.outstanding_total > 0 THEN 1 ELSE 0 END) as overdue_accounts,'
                ." SUM(CASE WHEN collection_account_states.priority_level = '{$critical}' THEN 1 ELSE 0 END) as critical_accounts"
            )
            ->groupBy('collection_account_states.collector_id')
            ->toBase()
            ->get()
            ->keyBy(fn ($row) => (int) $row->collector_id)
            ->all();
    }

    private function whereAttributedPromise(EloquentBuilder $query, array $ids): EloquentBuilder
    {
        return $query->whereIn('collector_id', $ids)
            ->orWhere(fn (EloquentBuilder $legacy) => $legacy->whereNull('collector_id')->whereIn('created_by', $ids));
    }

    /** @return array{0: string, 1: string} */
    private function bounds(array $period): array
    {
        return [
            $period['start']->format('Y-m-d H:i:s'),
            $period['end']->format('Y-m-d H:i:s'),
        ];
    }

    private function percent(float|int $numerator, float|int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }

    /** Daftar konstanta (bukan input user) sebagai literal SQL. */
    private function quotedList(array $values): string
    {
        return implode(', ', array_map(fn ($value) => "'".str_replace("'", "''", (string) $value)."'", $values));
    }

    /** @return list<int> */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
    }
}
