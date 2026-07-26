<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Models\PaymentPromise;
use App\Models\ResidentComplaint;
use App\Models\Unit;
use App\Services\PenaltyService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;

/**
 * Cluster-level tunggakan (arrears) analysis. Implements the spec's formula verbatim:
 * Persentase Unit Menunggak = Jumlah Unit Menunggak / Total Unit Aktif x 100%.
 * "Unit aktif" = units with status_id 'AK' (the occupied/active convention already used
 * throughout this app's fixtures/tests). Also feeds the "heatmap" - since clusters have no
 * geo-boundary data, the heatmap is this same per-cluster payload rendered as a
 * color-graded grid/treemap on the frontend rather than a literal map overlay.
 */
class SupervisorTunggakanController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupervisorAssignmentService $scopeService, PenaltyService $penaltyService)
    {
        $clusterIds = $scopeService->clusterIdsFor($request->user());
        $clusters = Cluster::query()->whereIn('id', $clusterIds)->orderBy('name')->get();

        $rows = $clusters->map(fn (Cluster $cluster) => $this->analyzeCluster($cluster, $penaltyService));

        return $this->success($rows->sortByDesc('priority_score')->values());
    }

    public function show(Request $request, string $cluster, SupervisorAssignmentService $scopeService, PenaltyService $penaltyService)
    {
        abort_unless(in_array($cluster, $scopeService->clusterIdsFor($request->user()), true), 403, 'Cluster ini bukan tanggung jawab Anda.');

        $clusterModel = Cluster::query()->findOrFail($cluster);
        $analysis = $this->analyzeCluster($clusterModel, $penaltyService);

        $units = Unit::query()->where('cluster_id', $cluster)->where('status_id', 'AK')->with('resident')->get();
        $unitDetails = $units->map(function (Unit $unit) use ($penaltyService) {
            $summary = $penaltyService->calculateUnitOutstanding($unit->id);

            return [
                'unit' => $unit,
                'total_outstanding' => round($summary['total_outstanding'], 2),
                'invoice_count' => $summary['invoice_count'],
                'is_menunggak' => $summary['total_outstanding'] > 0.01,
            ];
        })->filter(fn ($row) => $row['is_menunggak'])->values();

        return $this->success([
            'analysis' => $analysis,
            'units' => $unitDetails,
        ]);
    }

    private function analyzeCluster(Cluster $cluster, PenaltyService $penaltyService): array
    {
        $activeUnits = Unit::query()->where('cluster_id', $cluster->id)->where('status_id', 'AK')->get(['id']);
        $totalAktif = $activeUnits->count();

        $unitMenunggak = 0;
        $totalTunggakan = 0.0;

        foreach ($activeUnits as $unit) {
            $summary = $penaltyService->calculateUnitOutstanding($unit->id);
            if ($summary['total_outstanding'] > 0.01) {
                $unitMenunggak++;
                $totalTunggakan += $summary['total_outstanding'];
            }
        }

        $unitIds = $activeUnits->pluck('id');
        $brokenPromiseCount = PaymentPromise::query()->whereIn('unit_id', $unitIds)->where('status', 'broken')->count();
        $violationCount = ResidentComplaint::query()->whereIn('unit_id', $unitIds)->count();

        $percentage = $totalAktif > 0 ? round($unitMenunggak / $totalAktif * 100, 2) : 0.0;

        return [
            'cluster_id' => $cluster->id,
            'cluster_name' => $cluster->name,
            'total_unit_aktif' => $totalAktif,
            'unit_menunggak' => $unitMenunggak,
            'percentage_unit_menunggak' => $percentage,
            'total_tunggakan' => round($totalTunggakan, 2),
            'broken_promise_count' => $brokenPromiseCount,
            'violation_count' => $violationCount,
            'priority_score' => $this->priorityScore($percentage, $totalTunggakan, $brokenPromiseCount, $violationCount),
        ];
    }

    /**
     * Simple weighted composite so the highest-risk clusters sort first: arrears rate and
     * total outstanding amount dominate, broken promises and violations add smaller weight.
     * Not a statistically calibrated model - a deliberately simple, explainable score.
     */
    private function priorityScore(float $percentage, float $totalTunggakan, int $brokenPromiseCount, int $violationCount): float
    {
        return round(($percentage * 1.0) + (min($totalTunggakan / 1_000_000, 50) * 1.0) + ($brokenPromiseCount * 2) + ($violationCount * 1), 2);
    }
}
