<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\EmergencyAlert;
use App\Services\AuditService;
use Illuminate\Http\Request;

class EmergencyAlertController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = EmergencyAlert::query()
            ->with(['unit.cluster', 'resident', 'acknowledger'])
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function acknowledge(Request $request, EmergencyAlert $emergencyAlert, AuditService $auditService)
    {
        if ($emergencyAlert->status === 'acknowledged') {
            return $this->success($emergencyAlert, 'Sinyal darurat ini sudah ditandai ditangani.');
        }

        $old = $emergencyAlert->toArray();
        $emergencyAlert->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()->id,
            'acknowledged_at' => now(),
        ]);
        $auditService->log('emergency_alert_acknowledged', 'emergency-alerts', 'ACKNOWLEDGE', $emergencyAlert, $old, $emergencyAlert->refresh()->toArray());

        return $this->success($emergencyAlert->load(['unit.cluster', 'resident', 'acknowledger']), 'Sinyal darurat ditandai sudah ditangani.');
    }
}
