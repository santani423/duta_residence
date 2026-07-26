<?php

namespace App\Services;

use App\Models\CollectorTarget;
use App\Models\CollectorVisit;
use App\Models\PaymentTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;

class CollectorPerformanceService
{
    public function periodRange(string $periodType, string $periodStart): array
    {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();

        $end = match ($periodType) {
            CollectorTarget::PERIOD_DAILY => $start->endOfDay(),
            CollectorTarget::PERIOD_WEEKLY => $start->addDays(6)->endOfDay(),
            CollectorTarget::PERIOD_MONTHLY => $start->endOfMonth(),
            default => $start->endOfDay(),
        };

        return [$start, $end];
    }

    public function achievementFor(User $collector, string $periodType, string $periodStart): array
    {
        [$start, $end] = $this->periodRange($periodType, $periodStart);

        $collectedAmount = (float) PaymentTransaction::query()
            ->where('created_by', $collector->id)
            ->where('payment_provider', 'loket')
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total');

        $visitCount = CollectorVisit::query()
            ->where('collector_id', $collector->id)
            ->whereBetween('visit_date', [$start, $end])
            ->count();

        $target = CollectorTarget::query()
            ->where('collector_id', $collector->id)
            ->where('period_type', $periodType)
            ->where('period_start', $start->toDateString())
            ->first();

        $targetAmount = (float) ($target->target_amount ?? 0);
        $targetVisitCount = $target->target_visit_count ?? null;

        return [
            'period_type' => $periodType,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'target_amount' => $targetAmount,
            'collected_amount' => $collectedAmount,
            'achievement_percent' => $targetAmount > 0 ? round(min($collectedAmount / $targetAmount, 1) * 100, 1) : null,
            'target_visit_count' => $targetVisitCount,
            'visit_count' => $visitCount,
        ];
    }
}
