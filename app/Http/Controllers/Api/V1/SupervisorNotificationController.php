<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SupervisorNotification;
use App\Services\AuditService;
use App\Services\NotificationPresenter;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupervisorNotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupervisorAssignmentService $scopeService, NotificationPresenter $presenter)
    {
        $visible = $this->visibleQuery($request, $scopeService);
        $unreadCount = (clone $visible)->unread()->count();

        $query = $visible
            ->when($request->query('category'), fn ($q, $value) => $q->where('category', $value))
            ->when($request->query('priority'), fn ($q, $value) => $q->where('priority', $value))
            ->when($request->query('handled_status'), fn ($q, $value) => $q->where('handled_status', $value))
            ->when($request->query('read_status'), fn ($q, $value) => $q->where('read_status', $value))
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread())
            ->when($request->boolean('unhandled_only'), fn ($q) => $q->unhandled());

        $paginator = $query->latest()->latest('id')->paginate($request->integer('per_page', 20));
        $paginator->setCollection($paginator->getCollection()->map(fn (SupervisorNotification $n) => $presenter->supervisor($n)));

        return $this->paginated($paginator, extraMeta: ['unread_count' => $unreadCount]);
    }

    public function show(Request $request, SupervisorNotification $supervisorNotification, SupervisorAssignmentService $scopeService, NotificationPresenter $presenter)
    {
        $this->assertVisible($request, $supervisorNotification, $scopeService);

        return $this->success($presenter->supervisor($supervisorNotification));
    }

    public function markRead(Request $request, SupervisorNotification $supervisorNotification, SupervisorAssignmentService $scopeService, NotificationPresenter $presenter)
    {
        $this->assertVisible($request, $supervisorNotification, $scopeService);

        $supervisorNotification->update(['read_status' => 'read']);

        return $this->success($presenter->supervisor($supervisorNotification), 'Notifikasi ditandai sudah dibaca.');
    }

    public function markUnread(Request $request, SupervisorNotification $supervisorNotification, SupervisorAssignmentService $scopeService, NotificationPresenter $presenter)
    {
        $this->assertVisible($request, $supervisorNotification, $scopeService);

        $supervisorNotification->update(['read_status' => 'unread']);

        return $this->success($presenter->supervisor($supervisorNotification), 'Notifikasi ditandai belum dibaca.');
    }

    public function markAllRead(Request $request, SupervisorAssignmentService $scopeService)
    {
        $this->visibleQuery($request, $scopeService)->unread()->update(['read_status' => 'read']);

        return $this->success(null, 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function markHandled(Request $request, SupervisorNotification $supervisorNotification, SupervisorAssignmentService $scopeService)
    {
        $this->assertVisible($request, $supervisorNotification, $scopeService);

        $supervisorNotification->update([
            'handled_status' => SupervisorNotification::HANDLED_HANDLED,
            'read_status' => 'read',
        ]);

        return $this->success($supervisorNotification, 'Notifikasi ditandai sudah ditangani.');
    }

    public function escalate(Request $request, SupervisorNotification $supervisorNotification, AuditService $auditService, SupervisorAssignmentService $scopeService)
    {
        $this->assertVisible($request, $supervisorNotification, $scopeService);

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

    /**
     * Mirrors index()'s own scoping: system-wide notifications (no related_collector_id)
     * are visible to any supervisor with access to this center; collector-specific ones
     * must belong to a collector this supervisor is actually assigned to.
     */
    private function visibleQuery(Request $request, SupervisorAssignmentService $scopeService)
    {
        $collectorIds = $scopeService->collectorIdsFor($request->user());

        // System-wide notifications (related_collector_id null) are visible to everyone
        // with access to this center; collector-specific ones are scoped to this
        // supervisor's own collectors, same discipline as every other monitoring endpoint.
        return SupervisorNotification::query()
            ->where(fn ($q) => $q->whereNull('related_collector_id')->orWhereIn('related_collector_id', $collectorIds));
    }

    private function assertVisible(Request $request, SupervisorNotification $supervisorNotification, SupervisorAssignmentService $scopeService): void
    {
        abort_unless(
            is_null($supervisorNotification->related_collector_id)
                || $scopeService->isCollectorAssigned($request->user(), $supervisorNotification->related_collector_id),
            404
        );
    }
}
