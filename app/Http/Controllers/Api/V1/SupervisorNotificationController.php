<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SupervisorNotification;
use App\Services\AuditService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupervisorNotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupervisorAssignmentService $scopeService)
    {
        $collectorIds = $scopeService->collectorIdsFor($request->user());

        $query = SupervisorNotification::query()
            // System-wide notifications (related_collector_id null) are visible to everyone
            // with access to this center; collector-specific ones are scoped to this
            // supervisor's own collectors, same discipline as every other monitoring endpoint.
            ->where(fn ($q) => $q->whereNull('related_collector_id')->orWhereIn('related_collector_id', $collectorIds))
            ->when($request->query('category'), fn ($q, $value) => $q->where('category', $value))
            ->when($request->query('priority'), fn ($q, $value) => $q->where('priority', $value))
            ->when($request->query('handled_status'), fn ($q, $value) => $q->where('handled_status', $value))
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread())
            ->when($request->boolean('unhandled_only'), fn ($q) => $q->unhandled());

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function markRead(SupervisorNotification $supervisorNotification)
    {
        $supervisorNotification->update(['read_status' => 'read']);

        return $this->success($supervisorNotification, 'Notifikasi ditandai sudah dibaca.');
    }

    public function markHandled(Request $request, SupervisorNotification $supervisorNotification)
    {
        $supervisorNotification->update([
            'handled_status' => SupervisorNotification::HANDLED_HANDLED,
            'read_status' => 'read',
        ]);

        return $this->success($supervisorNotification, 'Notifikasi ditandai sudah ditangani.');
    }

    public function escalate(Request $request, SupervisorNotification $supervisorNotification, AuditService $auditService)
    {
        $data = $request->validate([
            'escalated_to' => ['required', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $supervisorNotification->escalation_log ?? [];
        $log[] = [
            'escalated_by' => $request->user()->id,
            'escalated_to' => $data['escalated_to'],
            'note' => $data['note'] ?? null,
            'escalated_at' => now()->toDateTimeString(),
        ];

        $supervisorNotification->update([
            'handled_status' => SupervisorNotification::HANDLED_ESCALATED,
            'responsible_user_id' => $data['escalated_to'],
            'escalation_log' => $log,
        ]);

        $auditService->log('supervisor_notification_escalated', 'supervisor-notifications', 'ESCALATE', $supervisorNotification, [], $supervisorNotification->toArray());

        return $this->success($supervisorNotification, 'Notifikasi berhasil dieskalasi.');
    }
}
