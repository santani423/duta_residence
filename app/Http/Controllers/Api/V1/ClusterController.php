<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClusterController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Cluster::query()->orderBy('name')->get());
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'size:2', Rule::unique('clusters', 'id')],
            'name' => ['required', 'string', 'max:50'],
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $cluster = Cluster::query()->create($data);
        ClusterRateSchedule::query()->create([
            'cluster_id' => $cluster->id,
            'rate' => $cluster->monthly_rate,
            'effective_date' => now()->toDateString(),
            'notes' => 'Tarif awal saat cluster dibuat.',
            'is_active' => true,
            'activated_at' => now(),
            'created_by' => $request->user()->id,
        ]);
        $auditService->log('cluster_created', 'clusters', 'CREATE', $cluster, [], $cluster->toArray());

        return $this->success($cluster, 'Cluster berhasil dibuat.', 201);
    }

    public function show(Cluster $cluster)
    {
        $cluster->loadCount('units');

        return $this->success([
            ...$cluster->toArray(),
            'current_rate_schedule' => $cluster->currentRateSchedule(),
            'next_rate_schedule' => $cluster->nextRateSchedule(),
        ]);
    }

    public function update(Request $request, Cluster $cluster, AuditService $auditService)
    {
        $data = $request->validate([
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $cluster->toArray();
        $cluster->update($data);
        $auditService->log('cluster_updated', 'clusters', 'UPDATE', $cluster, $old, $cluster->toArray());

        return $this->success($cluster->refresh(), 'Tarif klaster berhasil diperbarui.');
    }

    public function destroy(Cluster $cluster, AuditService $auditService)
    {
        if ($cluster->units()->exists()) {
            return $this->error('Cluster tidak dapat dihapus karena masih memiliki unit.', 422);
        }

        $old = $cluster->toArray();
        $cluster->delete();
        $auditService->log('cluster_deleted', 'clusters', 'DELETE', $cluster, $old, []);

        return $this->success(null, 'Cluster berhasil dihapus.');
    }
}
