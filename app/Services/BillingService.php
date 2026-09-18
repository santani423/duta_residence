<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly ClusterRateScheduleService $rateScheduleService,
        private readonly DiscountService $discountService,
    ) {}

    public function prepareMonthly(int $year, int $month, int $userId): Collection
    {
        return DB::transaction(function () use ($year, $month, $userId) {
            $units = Unit::query()
                ->with(['cluster', 'discountRule'])
                ->where('status_id', 'AK')
                ->get();

            // Bulk-resolve existing billings and cluster rates up front (2 queries) instead of
            // one rate lookup + one existence check per unit - avoids an N+1 query storm that
            // times out once the DB is not co-located with the app (e.g. a remote host).
            $existingBillings = Billing::query()
                ->whereIn('unit_id', $units->pluck('id'))
                ->where('year', $year)
                ->where('month', $month)
                ->get()
                ->keyBy('unit_id');

            $rates = $this->rateScheduleService->ratesForClusters($units->pluck('cluster')->unique('id'), $year, $month);

            return $units->map(function (Unit $unit) use ($existingBillings, $rates, $year, $month, $userId) {
                if ($existing = $existingBillings->get($unit->id)) {
                    return $existing;
                }

                $rate = $rates[$unit->cluster_id];
                $discount = $this->discountService->calculateForNewBilling($unit, $rate);

                return Billing::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'amount' => $rate,
                    'discount' => $discount['amount'],
                    'discount_rule_id' => $discount['rule']?->id,
                    'status_id' => '01',
                    'is_penalty_eligible' => $unit->is_penalty_eligible,
                    'is_discount_eligible' => $unit->is_discount_eligible,
                    'billing_type' => 'regular',
                    'created_by' => $userId,
                ]);
            });
        });
    }

    public function prepareSpecial(Unit $unit, int $year, int $month, float $amount, int $userId): Billing
    {
        $discount = $this->discountService->calculateForNewBilling($unit, $amount);

        return Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'amount' => $amount,
            'discount' => $discount['amount'],
            'discount_rule_id' => $discount['rule']?->id,
            'status_id' => '01',
            'billing_type' => 'special',
            'is_penalty_eligible' => $unit->is_penalty_eligible,
            'is_discount_eligible' => $unit->is_discount_eligible,
            'created_by' => $userId,
        ]);
    }

    /**
     * Create a backdated ("Tagihan Mundur") billing. The amount is never taken from caller
     * input - it is always resolved server-side from the IPL rate configured for the period
     * before $month (walking further back if needed), per ClusterRateScheduleService.
     */
    /**
     * First month a back-dated billing may cover for this unit: the month right after the last
     * period the unit has been billed for (cancelled billings don't count). A unit with no billing
     * yet starts from next month, since the current month is covered by regular monthly billing.
     */
    public function backStartPeriod(Unit $unit): Carbon
    {
        $last = Billing::query()
            ->where('unit_id', $unit->id)
            ->where('status_id', '!=', Billing::STATUS_CANCELLED)
            ->orderByDesc('year')->orderByDesc('month')
            ->first(['year', 'month']);

        return $last
            ? Carbon::create($last->year, $last->month, 1)->startOfDay()->addMonthNoOverflow()
            : now()->startOfMonth()->addMonthNoOverflow();
    }

    public function lastBilledPeriod(Unit $unit): ?Carbon
    {
        $start = $this->backStartPeriod($unit);
        $hasBilling = Billing::query()->where('unit_id', $unit->id)->where('status_id', '!=', Billing::STATUS_CANCELLED)->exists();

        return $hasBilling ? $start->copy()->subMonthNoOverflow() : null;
    }

    public function prepareBack(Unit $unit, int $year, int $month, int $userId): Billing
    {
        $resolved = $this->rateScheduleService->resolveRateForBackdatedPeriod($unit->cluster, $year, $month);
        $discount = $this->discountService->calculateForNewBilling($unit, $resolved['rate']);

        return Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'amount' => $resolved['rate'],
            'discount' => $discount['amount'],
            'discount_rule_id' => $discount['rule']?->id,
            'status_id' => '01',
            'billing_type' => 'back',
            'is_penalty_eligible' => $unit->is_penalty_eligible,
            'is_discount_eligible' => $unit->is_discount_eligible,
            'created_by' => $userId,
        ]);
    }

    public function approve(Billing $billing, int $userId, ?string $notes = null): Billing
    {
        $billing->forceFill([
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ])->save();

        return $billing->refresh();
    }
}
