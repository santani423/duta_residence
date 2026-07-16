<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\PenaltyRule;
use App\Models\PenaltySetting;
use App\Models\Unit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Single source of truth for tunggakan/denda calculations. Every page and API
 * (admin, resident portal, payment processing, PDF documents) must go through
 * this service so the numbers never diverge between screens.
 */
class PenaltyService
{
    /** @var array<string, Collection<int, PenaltyRule>> */
    private array $ruleCache = [];

    private ?PenaltySetting $settingCache = null;

    /**
     * umur_tunggakan = ((tahun_sekarang - tahun_tagihan) * 12) + (bulan_sekarang - bulan_tagihan),
     * clamped to a minimum of 0. Deliberately month-based (not day-based) per business rule:
     * a July invoice viewed in December is "5 bulan" regardless of which day of December it is.
     */
    public function calculateOverdueMonths(Billing $billing, ?CarbonInterface $date = null): int
    {
        $date = $date ?? now();
        $months = (($date->year - (int) $billing->year) * 12) + ($date->month - (int) $billing->month);

        return max(0, $months);
    }

    /**
     * Resolve the penalty tier that applies to this billing's overdue age, preferring a
     * cluster-specific rule over the global one when both cover the same overdue-month range.
     */
    public function resolveRule(Billing $billing, ?CarbonInterface $date = null): ?PenaltyRule
    {
        $date = $date ?? now();
        $overdueMonths = $this->calculateOverdueMonths($billing, $date);
        $clusterId = $billing->relationLoaded('unit') ? $billing->unit?->cluster_id : Unit::query()->whereKey($billing->unit_id)->value('cluster_id');

        return $this->rulesEffectiveOn($date)
            ->filter(fn (PenaltyRule $rule) => $rule->covers($overdueMonths) && ($rule->cluster_id === null || $rule->cluster_id === $clusterId))
            ->sortByDesc(fn (PenaltyRule $rule) => $rule->cluster_id !== null)
            ->first();
    }

    public function isPenaltyEnabled(): bool
    {
        return ($this->settingCache ??= PenaltySetting::current())->is_active;
    }

    /**
     * Single gate for "can this billing ever be charged a penalty right now" - used both to
     * decide the charged amount and to decide whether a penalty_rule is even shown, so the two
     * never disagree (e.g. rule shown with amount 0 while the global toggle is off).
     */
    public function isPenaltyApplicable(Billing $billing): bool
    {
        return $billing->is_penalty_eligible && $this->isPenaltyEnabled();
    }

    public function allocationOrder(): string
    {
        return ($this->settingCache ??= PenaltySetting::current())->allocation_order ?: PenaltySetting::ALLOCATION_PENALTY_FIRST;
    }

    /**
     * Denda berjenjang (tier), bukan akumulasi per bulan. Tagihan yang sudah lunas
     * mengembalikan nilai snapshot yang dibekukan saat pembayaran; tagihan dibatalkan selalu 0.
     */
    public function calculatePenalty(Billing $billing, ?CarbonInterface $date = null): float
    {
        if ($billing->isCancelled()) {
            return 0.0;
        }

        if ($billing->isPaid()) {
            return round((float) $billing->penalty, 2);
        }

        if (! $this->isPenaltyApplicable($billing)) {
            return 0.0;
        }

        $rule = $this->resolveRule($billing, $date);
        $gross = (float) ($rule->penalty_amount ?? 0);

        return max(0.0, round($gross - (float) $billing->penalty_waived_amount, 2));
    }

    /**
     * Tanggal jatuh tempo tampilan (bukan acuan tier bulan) - dapat dikonfigurasi lewat
     * grandduta.notification.penalty_day. Dipakai untuk status "overdue" pada bulan berjalan.
     */
    public function dueDate(Billing $billing): Carbon
    {
        $day = min((int) config('grandduta.notification.penalty_day', 20), 28);

        return Carbon::now()->setDate((int) $billing->year, (int) $billing->month, $day)->startOfDay();
    }

    public function invoiceStatus(Billing $billing, ?CarbonInterface $date = null): string
    {
        $date = $date ?? now();

        return match (true) {
            $billing->status_id === Billing::STATUS_CANCELLED => 'cancelled',
            $billing->status_id === Billing::STATUS_PAID => 'paid',
            $billing->status_id === Billing::STATUS_PARTIAL => 'partial',
            blank($billing->approved_at) => 'pending',
            $date->greaterThan($this->dueDate($billing)) => 'overdue',
            default => 'unpaid',
        };
    }

    public function calculateInvoiceTotal(Billing $billing, ?CarbonInterface $date = null): array
    {
        $date = $date ?? now();
        $overdueMonths = $this->calculateOverdueMonths($billing, $date);
        $rule = $this->isPenaltyApplicable($billing) ? $this->resolveRule($billing, $date) : null;
        $penaltyAmount = $this->calculatePenalty($billing, $date);

        $principalDue = max(0.0, (float) $billing->amount - (float) $billing->discount);
        $isClosed = $billing->isCancelled();
        $outstandingPrincipal = $isClosed ? 0.0 : max(0.0, $principalDue - (float) $billing->principal_paid);
        $outstandingPenalty = $isClosed ? 0.0 : max(0.0, $penaltyAmount - (float) $billing->penalty_paid);

        return [
            'invoice_id' => $billing->id,
            'unit_id' => $billing->unit_id,
            'period' => sprintf('%04d-%02d', $billing->year, $billing->month),
            'year' => (int) $billing->year,
            'month' => (int) $billing->month,
            'due_date' => $this->dueDate($billing)->toDateString(),
            'principal_amount' => round($principalDue, 2),
            'principal_paid' => round((float) $billing->principal_paid, 2),
            'outstanding_principal' => round($outstandingPrincipal, 2),
            'overdue_months' => $overdueMonths,
            'penalty_amount' => round($penaltyAmount, 2),
            'penalty_paid' => round((float) $billing->penalty_paid, 2),
            'penalty_waived_amount' => round((float) $billing->penalty_waived_amount, 2),
            'outstanding_penalty' => round($outstandingPenalty, 2),
            'total_amount' => round($principalDue + $penaltyAmount, 2),
            'total_paid' => round((float) $billing->principal_paid + (float) $billing->penalty_paid, 2),
            'total_outstanding' => round($outstandingPrincipal + $outstandingPenalty, 2),
            'status_id' => $billing->status_id,
            'status' => $this->invoiceStatus($billing, $date),
            'penalty_rule' => $rule ? [
                'id' => $rule->id,
                'name' => $rule->name,
                'minimum_overdue_month' => $rule->minimum_overdue_month,
                'maximum_overdue_month' => $rule->maximum_overdue_month,
                'amount' => (float) $rule->penalty_amount,
            ] : null,
        ];
    }

    public function calculateUnitOutstanding(string $unitId, ?CarbonInterface $date = null): array
    {
        $billings = Billing::query()->with('unit')->where('unit_id', $unitId)->outstanding()
            ->orderBy('year')->orderBy('month')->get();

        return $this->summarize($billings, $date);
    }

    public function calculateCustomerOutstanding(string $residentId, ?CarbonInterface $date = null): array
    {
        $unitIds = Unit::query()->where('resident_id', $residentId)->pluck('id');
        $billings = Billing::query()->with('unit')->whereIn('unit_id', $unitIds)->outstanding()
            ->orderBy('year')->orderBy('month')->get();

        return $this->summarize($billings, $date);
    }

    /**
     * Preload every effective rule once per calculation date instead of querying per-invoice,
     * so batch screens (billing list, outstanding summaries) stay O(1) queries instead of N+1.
     */
    private function rulesEffectiveOn(CarbonInterface $date): Collection
    {
        $key = $date->toDateString();

        return $this->ruleCache[$key] ??= PenaltyRule::query()->effectiveOn($date)->get();
    }

    private function summarize(Collection $billings, ?CarbonInterface $date): array
    {
        $items = $billings->map(fn (Billing $billing) => $this->calculateInvoiceTotal($billing, $date));

        return [
            'items' => $items->values(),
            'invoice_count' => $items->count(),
            'total_principal_outstanding' => round($items->sum('outstanding_principal'), 2),
            'total_penalty_outstanding' => round($items->sum('outstanding_penalty'), 2),
            'total_outstanding' => round($items->sum('total_outstanding'), 2),
        ];
    }
}
