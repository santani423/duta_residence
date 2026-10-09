<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorLocation;
use App\Models\EmergencyAlert;
use App\Models\User;
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

        // Termasuk SOS collector (unit_id NULL) dari collector dalam cakupan - lihat
        // SupervisorDashboardController::constrainEmergencies.
        $emergencies = EmergencyAlert::query()
            ->where('status', 'active')
            ->tap(fn ($q) => SupervisorDashboardController::constrainEmergencies($q, $scopeService->hasFullScope($request->user()), $unitIds, $collectorIds))
            ->with(['unit.cluster', 'resident'])
            ->latest()
            ->get();

        // Pengirim alert (tambahan field `reporter`, agar SOS tanpa unit tetap bisa dikenali).
        $reporters = User::query()->whereIn('id', $emergencies->pluck('created_by')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $emergencies->each(fn (EmergencyAlert $alert) => $alert->setRelation('reporter', $reporters->get($alert->created_by)));

        return $this->success([
            'collector_locations' => $locations,
            'active_emergencies' => $emergencies,
        ]);
    }
}
