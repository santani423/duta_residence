<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\CollectionAccountState;
use App\Models\CollectionActivity;
use App\Models\CollectorReminder;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Collection account = unit. Service ini menghitung status penagihan satu/banyak unit
 * (outstanding, aging, status, prioritas, kontak terakhir, follow-up berikutnya) dari data
 * sumber (billings, PTP, visit, aktivitas) dan menyimpannya ke cache collection_account_states.
 *
 * Outstanding selalu dihitung lewat PenaltyService (sumber tunggal angka tunggakan/denda) dan
 * hanya mencakup tagihan yang sudah disetujui & belum lunas - sama dengan yang bisa dibayar
 * di PaymentService - sehingga angka collector tidak pernah berbeda dari loket/portal.
 */
class CollectionAccountService
{
    private const CHUNK = 200;

    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly CollectionAgingService $agingService,
        private readonly CollectionPriorityService $priorityService,
        private readonly CollectorAssignmentService $assignmentService,
    ) {}

    public function refresh(string $unitId, ?CarbonInterface $date = null): ?CollectionAccountState
    {
        $this->refreshMany([$unitId], $date);

        return CollectionAccountState::query()->find($unitId);
    }

    /** @return int jumlah state yang ditulis */
    public function refreshMany(array $unitIds, ?CarbonInterface $date = null): int
    {
        $written = 0;

        foreach (array_chunk(array_values(array_unique($unitIds)), self::CHUNK) as $chunk) {
            $computed = $this->computeMany($chunk, $date);

            foreach ($computed as $unitId => $attributes) {
                CollectionAccountState::query()->updateOrCreate(['unit_id' => $unitId], $attributes);
                $written++;
            }

            // Unit yang sudah dihapus (soft delete) tidak lagi menjadi akun penagihan.
            $missing = array_diff($chunk, array_keys($computed));
            if ($missing) {
                CollectionAccountState::query()->whereIn('unit_id', $missing)->delete();
            }
        }

        return $written;
    }

    /** Refresh seluruh unit secara bertahap; dipakai scheduler & backfill awal. */
    public function refreshAll(?CarbonInterface $date = null, ?callable $onChunk = null): int
    {
        $written = 0;

        Unit::query()->select('id')->orderBy('id')->chunk(500, function (Collection $units) use ($date, $onChunk, &$written) {
            $count = $this->refreshMany($units->pluck('id')->all(), $date);
            $written += $count;
            if ($onChunk) {
                $onChunk($count);
            }
        });

        return $written;
    }

    /**
     * Hitung atribut state tanpa menyimpan.
     *
     * @return array<string, array<string, mixed>> unit_id => atribut CollectionAccountState
     */
    public function computeMany(array $unitIds, ?CarbonInterface $date = null): array
    {
        $date = Carbon::instance($date ?? now());
        $today = $date->copy()->startOfDay();

        $units = Unit::query()
            ->whereIn('id', $unitIds)
            ->with(['billings' => fn ($q) => $q->outstanding()->approved()->orderBy('year')->orderBy('month')])
            ->get();

        if ($units->isEmpty()) {
            return [];
        }

        $ids = $units->pluck('id')->all();
        $context = $this->loadContext($ids, $today);
        $collectors = $this->assignmentService->primaryCollectorIdsFor($units);

        $result = [];
        foreach ($units as $unit) {
            $result[$unit->id] = $this->computeState($unit, $context, $collectors[$unit->id] ?? null, $date, $today);
        }

        return $result;
    }

    /**
     * Status akun - urutan prioritas, kondisi pertama yang cocok dipakai.
     *
     * @param  array{outstanding_total: float, is_escalated?: bool, is_disputed?: bool, has_active_promise?: bool, oldest_due_date: ?CarbonInterface, has_partial: bool}  $facts
     */
    public function resolveStatus(array $facts, CarbonInterface $today): string
    {
        $oldestDue = $facts['oldest_due_date'];
        $dueSoonDays = (int) config('collector.due_soon_days', 7);

        return match (true) {
            $facts['outstanding_total'] <= 0 => CollectionAccountState::STATUS_PAID,
            ! empty($facts['is_escalated']) => CollectionAccountState::STATUS_ESCALATED,
            ! empty($facts['is_disputed']) => CollectionAccountState::STATUS_DISPUTED,
            ! empty($facts['has_active_promise']) => CollectionAccountState::STATUS_PROMISE_TO_PAY,
            $oldestDue !== null && $oldestDue->lessThan($today) => CollectionAccountState::STATUS_OVERDUE,
            $facts['has_partial'] => CollectionAccountState::STATUS_PARTIALLY_PAID,
            $oldestDue !== null && $oldestDue->isSameDay($today) => CollectionAccountState::STATUS_DUE_TODAY,
            $oldestDue !== null && $oldestDue->lessThanOrEqualTo($today->copy()->addDays($dueSoonDays)) => CollectionAccountState::STATUS_DUE_SOON,
            default => CollectionAccountState::STATUS_CURRENT,
        };
    }

    /**
     * Rincian tagihan terbuka satu unit (untuk detail akun & tab Bills), dihitung segar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openInvoices(Unit $unit, ?CarbonInterface $date = null): Collection
    {
        $billings = $unit->relationLoaded('billings')
            ? $unit->billings
            : $unit->billings()->outstanding()->approved()->orderBy('year')->orderBy('month')->get();

        return $billings
            ->filter(fn (Billing $billing) => $billing->isOutstanding() && filled($billing->approved_at))
            ->map(function (Billing $billing) use ($unit, $date) {
                $billing->setRelation('unit', $unit);

                return $this->penaltyService->calculateInvoiceTotal($billing, $date);
            })
            ->filter(fn (array $invoice) => $invoice['total_outstanding'] > 0)
            ->values();
    }

    private function computeState(Unit $unit, array $context, ?int $collectorId, Carbon $date, Carbon $today): array
    {
        $invoices = $this->openInvoices($unit, $date);
        $dueDates = $invoices->map(fn (array $invoice) => Carbon::parse($invoice['due_date'])->startOfDay());
        $oldestDue = $dueDates->min();
        $nextDue = $dueDates->filter(fn (Carbon $due) => $due->greaterThanOrEqualTo($today))->min();

        $outstandingPrincipal = round($invoices->sum('outstanding_principal'), 2);
        $outstandingPenalty = round($invoices->sum('outstanding_penalty'), 2);
        $outstandingTotal = round($outstandingPrincipal + $outstandingPenalty, 2);

        $agingDays = $this->agingService->agingDays($oldestDue, $today);
        $activePromise = $context['active_promises']->get($unit->id);
        $brokenCount = (int) ($context['broken_ptp']->get($unit->id) ?? 0);
        $failedContacts = (int) ($context['failed_contacts']->get($unit->id) ?? 0);
        $failedVisits = (int) ($context['failed_visits']->get($unit->id) ?? 0);

        $status = $this->resolveStatus([
            'outstanding_total' => $outstandingTotal,
            'has_active_promise' => $activePromise !== null,
            'oldest_due_date' => $oldestDue,
            'has_partial' => $invoices->contains(fn (array $invoice) => $invoice['status_id'] === Billing::STATUS_PARTIAL),
        ], $today);

        $priority = $this->priorityService->evaluate([
            'aging_days' => $agingDays,
            'outstanding_total' => $outstandingTotal,
            'broken_ptp_count' => $brokenCount,
            'failed_contact_count' => $failedContacts,
            'failed_visit_count' => $failedVisits,
            'is_disputed' => $status === CollectionAccountState::STATUS_DISPUTED,
        ]);

        [$lastContactAt, $lastContactResult] = $this->lastContact($unit->id, $context);

        return [
            'customer_resident_id' => $unit->billingPayerResidentId(),
            'collector_id' => $collectorId,
            'outstanding_principal' => $outstandingPrincipal,
            'outstanding_penalty' => $outstandingPenalty,
            'outstanding_total' => $outstandingTotal,
            'open_invoice_count' => $invoices->count(),
            'oldest_due_date' => $oldestDue?->toDateString(),
            'next_due_date' => $nextDue?->toDateString(),
            'aging_days' => $agingDays,
            'aging_bucket' => $this->agingService->bucketFor($agingDays),
            'status' => $status,
            'priority_score' => $priority['score'],
            'priority_level' => $priority['level'],
            'last_contact_at' => $lastContactAt,
            'last_contact_result' => $lastContactResult,
            'next_follow_up_at' => $this->nextFollowUp($unit->id, $context, $activePromise, $today),
            'failed_contact_count' => $failedContacts,
            'failed_visit_count' => $failedVisits,
            'broken_ptp_count' => $brokenCount,
            'active_promise_id' => $activePromise?->id,
            'refreshed_at' => now(),
        ];
    }

    /**
     * Semua data pendukung untuk satu chunk unit dimuat dengan query agregat per sumber
     * (bukan per unit) supaya refresh ribuan unit tetap O(chunk) query, bukan N+1.
     */
    private function loadContext(array $unitIds, Carbon $today): array
    {
        $lookback = config('collector.lookback_days');
        $contactAndVisitTypes = [...CollectionActivity::CONTACT_TYPES, CollectionActivity::TYPE_VISIT];

        return [
            // PTP pending terdekat yang belum lewat tanggal janji (yang lewat akan di-broken oleh job).
            'active_promises' => PaymentPromise::query()
                ->whereIn('unit_id', $unitIds)
                ->where('status', PaymentPromise::STATUS_PENDING)
                ->whereDate('promised_date', '>=', $today)
                ->orderBy('promised_date')
                ->orderBy('id')
                ->get(['id', 'unit_id', 'promised_date', 'follow_up_date'])
                ->unique('unit_id')
                ->keyBy('unit_id'),

            'broken_ptp' => PaymentPromise::query()
                ->whereIn('unit_id', $unitIds)
                ->where('status', PaymentPromise::STATUS_BROKEN)
                // Data lama tidak punya broken_at; updated_at adalah perkiraan terbaik kapan status diubah.
                ->whereRaw('COALESCE(broken_at, updated_at) >= ?', [$today->copy()->subDays($lookback['broken_ptp'])])
                ->groupBy('unit_id')
                ->selectRaw('unit_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'unit_id'),

            'failed_contacts' => CollectionActivity::query()
                ->whereIn('unit_id', $unitIds)
                ->whereIn('type', CollectionActivity::CONTACT_TYPES)
                ->whereIn('channel_result', CollectionActivity::FAILED_RESULTS)
                ->where('occurred_at', '>=', $today->copy()->subDays($lookback['failed_contact']))
                ->groupBy('unit_id')
                ->selectRaw('unit_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'unit_id'),

            'failed_visits' => CollectorVisit::query()
                ->whereIn('unit_id', $unitIds)
                ->where('visit_date', '>=', $today->copy()->subDays($lookback['failed_visit']))
                ->where(fn ($q) => $q
                    ->where('lifecycle', CollectorVisit::LIFECYCLE_FAILED)
                    ->orWhereIn('result_code', CollectorVisit::FAILED_RESULT_CODES)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('result_code')->whereIn('status', CollectorVisit::FAILED_STATUSES)))
                ->groupBy('unit_id')
                ->selectRaw('unit_id, COUNT(*) as aggregate')
                ->pluck('aggregate', 'unit_id'),

            // Aktivitas kontak/visit terakhir per unit (untuk waktu & hasil kontak terakhir). Visit yang
            // baru dimulai (mis. Selesai menunggu tanda tangan penghuni) belum dihitung sebagai kontak.
            'last_activities' => CollectionActivity::query()
                ->whereIn('unit_id', $unitIds)
                ->whereIn('type', $contactAndVisitTypes)
                ->where(fn ($q) => $q->where('type', '<>', CollectionActivity::TYPE_VISIT)->orWhere('event', '<>', CollectorVisit::LIFECYCLE_IN_PROGRESS))
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(['id', 'unit_id', 'occurred_at', 'channel_result'])
                ->unique('unit_id')
                ->keyBy('unit_id'),

            // Follow-up terbaru yang dijanjikan collector lewat aktivitas.
            'latest_follow_ups' => CollectionActivity::query()
                ->whereIn('unit_id', $unitIds)
                ->whereNotNull('next_follow_up_at')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(['id', 'unit_id', 'next_follow_up_at'])
                ->unique('unit_id')
                ->pluck('next_follow_up_at', 'unit_id'),

            // Sumber lama yang mungkin belum tercatat sebagai aktivitas (data sebelum modul ini).
            'last_visits' => CollectorVisit::query()
                ->whereIn('unit_id', $unitIds)
                ->whereIn('lifecycle', [CollectorVisit::LIFECYCLE_COMPLETED, CollectorVisit::LIFECYCLE_FAILED])
                ->groupBy('unit_id')
                ->selectRaw('unit_id, MAX(visit_date) as aggregate')
                ->pluck('aggregate', 'unit_id'),

            'last_reminders' => CollectorReminder::query()
                ->whereIn('unit_id', $unitIds)
                ->groupBy('unit_id')
                ->selectRaw('unit_id, MAX(sent_at) as aggregate')
                ->pluck('aggregate', 'unit_id'),

            'next_visits' => CollectorVisit::query()
                ->whereIn('unit_id', $unitIds)
                ->where(fn ($q) => $q
                    ->where(fn ($s) => $s->where('lifecycle', CollectorVisit::LIFECYCLE_SCHEDULED)->whereDate('scheduled_date', '>=', $today))
                    ->orWhereDate('next_visit_date', '>=', $today))
                ->get(['unit_id', 'lifecycle', 'scheduled_date', 'next_visit_date'])
                ->groupBy('unit_id')
                ->map(fn (Collection $visits) => $visits
                    ->map(fn (CollectorVisit $v) => $v->lifecycle === CollectorVisit::LIFECYCLE_SCHEDULED && $v->scheduled_date?->greaterThanOrEqualTo($today)
                        ? $v->scheduled_date
                        : $v->next_visit_date)
                    ->filter(fn ($d) => $d !== null && $d->greaterThanOrEqualTo($today))
                    ->min()),
        ];
    }

    /** @return array{0: ?Carbon, 1: ?string} */
    private function lastContact(string $unitId, array $context): array
    {
        $activity = $context['last_activities']->get($unitId);

        $candidates = collect([
            $activity?->occurred_at,
            $this->toCarbon($context['last_visits']->get($unitId)),
            $this->toCarbon($context['last_reminders']->get($unitId)),
        ])->filter();

        $latest = $candidates->max();
        $result = ($activity && $latest && $activity->occurred_at->equalTo($latest)) ? $activity->channel_result : null;

        return [$latest, $result];
    }

    private function nextFollowUp(string $unitId, array $context, ?PaymentPromise $activePromise, Carbon $today): ?Carbon
    {
        return collect([
            $activePromise?->promised_date,
            $activePromise?->follow_up_date,
            $this->toCarbon($context['latest_follow_ups']->get($unitId)),
            $context['next_visits']->get($unitId),
        ])
            ->filter(fn ($date) => $date !== null && $date->greaterThanOrEqualTo($today))
            ->min();
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof CarbonInterface ? Carbon::instance($value) : Carbon::parse($value);
    }
}
