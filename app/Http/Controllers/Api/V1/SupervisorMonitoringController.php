<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorTarget;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\CollectorPerformanceService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grouped read-only monitoring endpoints (target/progress, PTP & broken-promise, payment
 * monitoring) - each reuses an already-proven query/service rather than re-deriving figures,
 * scoped through SupervisorAssignmentService so a supervisor only ever sees their own
 * collectors'/clusters' data.
 */
class SupervisorMonitoringController extends Controller
{
    use ApiResponse;

    public function targets(Request $request, SupervisorAssignmentService $scopeService, CollectorPerformanceService $performanceService)
    {
        $data = $request->validate([
            'period_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'period_start' => ['nullable', 'date'],
        ]);

        $collectorIds = $scopeService->collectorIdsFor($request->user());
        $periodType = $data['period_type'] ?? 'monthly';
        $periodStart = $data['period_start'] ?? now()->startOfMonth()->toDateString();

        $targets = CollectorTarget::query()
            ->whereIn('collector_id', $collectorIds)
            ->where('period_type', $periodType)
            ->whereDate('period_start', $periodStart)
            ->with('collector')
            ->get();

        $rows = $targets->map(function (CollectorTarget $target) use ($performanceService, $periodType, $periodStart) {
            $achievement = $performanceService->achievementFor($target->collector, $periodType, $periodStart);

            return [
                'collector' => $target->collector,
                'target' => $target,
                'achievement' => $achievement,
            ];
        });

        return $this->success($rows);
    }

    public function paymentPromises(Request $request, SupervisorAssignmentService $scopeService)
    {
        $query = PaymentPromise::query()
            ->whereIn('unit_id', $scopeService->unitIdsFor($request->user()))
            ->with(['unit.cluster', 'unit.resident'])
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->boolean('nearing_due'), fn ($q) => $q->where('status', 'pending')->whereDate('promised_date', '<=', now()->addDays(2)->toDateString()));

        return $this->paginated($query->latest('promised_date')->paginate($request->integer('per_page', 20)));
    }

    public function payments(Request $request, SupervisorAssignmentService $scopeService)
    {
        $collectorIds = $scopeService->collectorIdsFor($request->user());

        $query = PaymentTransaction::query()
            ->with(['unit.cluster', 'creator'])
            ->when($collectorIds, fn ($q) => $q->whereIn('created_by', $collectorIds))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('payment_method'), fn ($q, $value) => $q->where('payment_method', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 20)));
    }
}
