<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Services\AuditService;
use App\Services\ClusterRateScheduleService;
use Illuminate\Http\Request;

class ClusterRateScheduleController extends Controller
{
    use ApiResponse;

    public function index(Cluster $cluster)
    {
        return $this->success($cluster->rateSchedules()->with(['creator', 'updater'])->get());
    }

    public function store(Request $request, Cluster $cluster, ClusterRateScheduleService $service, AuditService $auditService)
    {
        $data = $this->validateSchedule($request);
        $service->assertNoConflict($cluster->id, $data['effective_date']);

        $data['cluster_id'] = $cluster->id;
        $data['created_by'] = $request->user()->id;
        $schedule = ClusterRateSchedule::query()->create($data);

        $auditService->log('cluster_rate_schedule_created', 'clusters', 'RATE_SCHEDULE_CREATE', $schedule, [], $schedule->toArray());

        if ($schedule->effective_date->gt(today())) {
            $service->notifyScheduled($schedule);
        }
        $service->syncClusterRate($cluster);

        return $this->success($schedule->fresh(['creator']), 'Jadwal tarif berhasil dibuat.', 201);
    }

    public function update(Request $request, ClusterRateSchedule $schedule, ClusterRateScheduleService $service, AuditService $auditService)
    {
        $service->assertMutable($schedule);
        $data = $this->validateSchedule($request, $schedule);
        $service->assertNoConflict($schedule->cluster_id, $data['effective_date'], $schedule->id);

        $old = $schedule->toArray();
        $data['updated_by'] = $request->user()->id;
        $schedule->update($data);

        $auditService->log('cluster_rate_schedule_updated', 'clusters', 'RATE_SCHEDULE_UPDATE', $schedule, $old, $schedule->toArray());

        $cluster = $schedule->cluster;
        if ($schedule->effective_date->gt(today())) {
            $service->notifyScheduled($schedule);
        }
        $service->syncClusterRate($cluster);

        return $this->success($schedule->fresh(['creator', 'updater']), 'Jadwal tarif berhasil diperbarui.');
    }

    public function destroy(ClusterRateSchedule $schedule, ClusterRateScheduleService $service, AuditService $auditService)
    {
        $service->assertMutable($schedule);

        $old = $schedule->toArray();
        $schedule->delete();

        $auditService->log('cluster_rate_schedule_deleted', 'clusters', 'RATE_SCHEDULE_DELETE', $schedule, $old, []);

        return $this->success(null, 'Jadwal tarif berhasil dihapus.');
    }

    private function validateSchedule(Request $request, ?ClusterRateSchedule $schedule = null): array
    {
        return $request->validate([
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
