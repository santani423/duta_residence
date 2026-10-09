<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorTarget;
use App\Services\CollectionScopeService;
use App\Services\CollectorPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ranking performa collector per periode, dibatasi cakupan user (supervisor → collector yang
 * menyentuh clusternya).
 */
class CollectionPerformanceController extends Controller
{
    use ApiResponse;

    public function ranking(Request $request, CollectionScopeService $scope, CollectorPerformanceService $performance)
    {
        $data = $request->validate([
            'period_type' => ['nullable', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['nullable', 'date'],
            'metric' => ['nullable', Rule::in(CollectorPerformanceService::RANKING_METRICS)],
            'cluster_id' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $clusterId = $data['cluster_id'] ?? null;
        if ($clusterId) {
            $scope->assertClusterInScope($user, $clusterId);
        }

        $period = $performance->resolvePeriod($data['period_type'] ?? CollectorTarget::PERIOD_MONTHLY, $data['period_start'] ?? null);
        $metric = $data['metric'] ?? 'collected_amount';

        $collectorsQuery = $scope->allCollectorsQuery($user);
        if ($clusterId) {
            $performance->constrainCollectorsToCluster($collectorsQuery, $clusterId);
        }
        $ids = $collectorsQuery->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $ranking = $performance->ranking($ids, $period, $metric);
        $rates = array_values(array_filter(array_column($ranking, 'collection_rate'), fn ($rate) => $rate !== null));

        return $this->success([
            'period' => $performance->periodPayload($period),
            'metric' => $metric,
            'summary' => [
                'collected_amount' => round(array_sum(array_column($ranking, 'collected_amount')), 2),
                'visit_count' => array_sum(array_column($ranking, 'visit_count')),
                'ptp_fulfilled' => array_sum(array_column($ranking, 'ptp_fulfilled')),
                'average_collection_rate' => $rates ? round(array_sum($rates) / count($rates), 1) : null,
                'collector_count' => count($ranking),
            ],
            'ranking' => $ranking,
        ]);
    }
}
