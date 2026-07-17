<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClusterMapComponentType;
use App\Models\ClusterMapObject;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClusterMapComponentTypeController extends Controller
{
    use ApiResponse;

    private const SHAPE_TYPES = ['rect', 'circle', 'triangle', 'trapezoid', 'polygon', 'line', 'text'];

    public function index()
    {
        return $this->success(
            ClusterMapComponentType::query()->orderBy('category')->orderBy('name')->get()
        );
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', Rule::unique('cluster_map_component_types', 'code')],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'fill_color' => ['required', 'string', 'max:20'],
            'stroke_color' => ['required', 'string', 'max:20'],
            'default_shape_type' => ['required', Rule::in(self::SHAPE_TYPES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $componentType = ClusterMapComponentType::query()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        $auditService->log('cluster_map_component_type_created', 'cluster-maps', 'CREATE', $componentType, [], $componentType->toArray());

        return $this->success($componentType, 'Komponen peta berhasil dibuat.', 201);
    }

    public function update(Request $request, ClusterMapComponentType $clusterMapComponentType, AuditService $auditService)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'fill_color' => ['required', 'string', 'max:20'],
            'stroke_color' => ['required', 'string', 'max:20'],
            'default_shape_type' => ['required', Rule::in(self::SHAPE_TYPES)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $clusterMapComponentType->toArray();
        $clusterMapComponentType->update($data);
        $auditService->log('cluster_map_component_type_updated', 'cluster-maps', 'UPDATE', $clusterMapComponentType, $old, $clusterMapComponentType->toArray());

        return $this->success($clusterMapComponentType->refresh(), 'Komponen peta berhasil diperbarui.');
    }

    public function destroy(ClusterMapComponentType $clusterMapComponentType, AuditService $auditService)
    {
        if (ClusterMapObject::query()->where('component_type_id', $clusterMapComponentType->id)->exists()) {
            return $this->error('Komponen tidak dapat dihapus karena masih digunakan pada peta cluster.', 422);
        }

        $old = $clusterMapComponentType->toArray();
        $clusterMapComponentType->delete();
        $auditService->log('cluster_map_component_type_deleted', 'cluster-maps', 'DELETE', $clusterMapComponentType, $old, []);

        return $this->success(null, 'Komponen peta berhasil dihapus.');
    }
}
