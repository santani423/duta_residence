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

    /** Staff/collector-initiated SOS — e.g. a collector in distress in the field with no specific unit context. */
    public function store(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'unit_id' => ['nullable', 'exists:units,id'],
            'note' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $alert = EmergencyAlert::query()->create([
            ...$data,
            'status' => 'active',
            'location_captured_at' => isset($data['latitude']) ? now() : null,
            'created_by' => $request->user()->id,
        ]);

        $auditService->log('emergency_alert_created', 'emergency-alerts', 'CREATE', $alert, [], $alert->toArray());

        return $this->success($alert->load(['unit.cluster', 'resident']), 'Sinyal darurat berhasil dikirim.', 201);
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
