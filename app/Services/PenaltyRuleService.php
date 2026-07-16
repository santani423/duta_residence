<?php

namespace App\Services;

use App\Models\PenaltyRule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PenaltyRuleService
{
    /**
     * A new/edited tier must not overlap another active rule in the same scope (same cluster,
     * or both global) across both the overdue-month range and the effective-date range at once.
     */
    public function assertNoOverlap(array $data, ?PenaltyRule $ignore = null): void
    {
        $minMonth = (int) $data['minimum_overdue_month'];
        $maxMonth = $data['maximum_overdue_month'] ?? null;
        $start = Carbon::parse($data['effective_start_date']);
        $end = ! empty($data['effective_end_date']) ? Carbon::parse($data['effective_end_date']) : null;
        $clusterId = $data['cluster_id'] ?? null;

        $conflict = PenaltyRule::query()
            ->where('is_active', true)
            ->when($clusterId === null, fn ($q) => $q->whereNull('cluster_id'), fn ($q) => $q->where('cluster_id', $clusterId))
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->get()
            ->first(function (PenaltyRule $rule) use ($minMonth, $maxMonth, $start, $end) {
                $monthsOverlap = $minMonth <= ($rule->maximum_overdue_month ?? PHP_INT_MAX)
                    && $rule->minimum_overdue_month <= ($maxMonth ?? PHP_INT_MAX);

                if (! $monthsOverlap) {
                    return false;
                }

                return $start->lte($rule->effective_end_date ?? Carbon::maxValue())
                    && $rule->effective_start_date->lte($end ?? Carbon::maxValue());
            });

        if ($conflict) {
            throw ValidationException::withMessages([
                'minimum_overdue_month' => ["Rentang tunggakan tumpang tindih dengan aturan \"{$conflict->name}\" (#{$conflict->id})."],
            ]);
        }
    }
}
