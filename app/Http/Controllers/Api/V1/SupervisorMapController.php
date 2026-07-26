<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorLocation;
use App\Models\EmergencyAlert;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;

/**
 * Supervisor's live map - the same "latest ping per collector" data already powering
 * CollectorLiveMapPage.jsx's admin map, but scoped down to only the collectors this
 * supervisor oversees, plus overlays (high-tunggakan clusters, active emergencies in
 * scope) so Leaflet can render everything a Supervisor needs on one map.
 */
class SupervisorMapController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupervisorAssignmentService $scopeService)
    {
        $collectorIds = $scopeService->collectorIdsFor($request->user());
        $unitIds = $scopeService->unitIdsFor($request->user());

        $latestIds = CollectorLocation::query()
            ->whereIn('collector_id', $collectorIds)
            ->selectRaw('MAX(id) as id')
            ->groupBy('collector_id');

        $locations = CollectorLocation::query()->with('collector.collectorProfile')->whereIn('id', $latestIds)->get();

        $emergencies = EmergencyAlert::query()
            ->where('status', 'active')
            ->whereIn('unit_id', $unitIds)
            ->with(['unit.cluster', 'resident'])
            ->get();

        return $this->success([
            'collector_locations' => $locations,
            'active_emergencies' => $emergencies,
        ]);
    }
}
