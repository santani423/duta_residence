<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Cluster;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ClusterController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Cluster::query()->orderBy('name')->get());
    }

    public function show(Cluster $cluster)
    {
        return $this->success($cluster->loadCount('customers'));
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
}
