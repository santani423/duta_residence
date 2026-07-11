<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Unit::query()
            ->with(['cluster', 'propertyType', 'status', 'occupancy', 'resident'])
            ->search($request->query('search'))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->when($request->query('property_type_id'), fn ($q, $value) => $q->where('property_type_id', $value))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->where('resident_id', $value));

        return $this->paginated($query->orderBy('cluster_id')->orderBy('block')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateUnit($request);
        $data['created_by'] = $request->user()->id;
        $unit = Unit::query()->create($data);
        $auditService->log('unit_created', 'units', 'CREATE', $unit, [], $unit->toArray());

        return $this->success($unit->load(['cluster', 'status', 'resident']), 'Unit berhasil dibuat.', 201);
    }

    public function show(Unit $unit)
    {
        return $this->success($unit->load(['cluster', 'propertyType', 'occupancy', 'status', 'resident', 'billings.status']));
    }

    public function update(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $this->validateUnit($request, $unit);
        $data['updated_by'] = $request->user()->id;
        $old = $unit->toArray();
        $unit->update($data);
        $auditService->log('unit_updated', 'units', 'UPDATE', $unit, $old, $unit->toArray());

        return $this->success($unit->refresh()->load(['cluster', 'status', 'resident']), 'Unit berhasil diperbarui.');
    }

    public function destroy(Unit $unit, AuditService $auditService)
    {
        $old = $unit->toArray();
        $unit->delete();
        $auditService->log('unit_deleted', 'units', 'DELETE', $unit, $old, []);

        return $this->success(null, 'Unit berhasil dihapus.');
    }

    public function convertProperty(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $request->validate([
            'property_type_id' => ['required', Rule::in(['B'])],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        if ($unit->property_type_id !== 'K') {
            return $this->error('Hanya kavling developer yang dapat dikonversi menjadi bangunan.', 422);
        }

        $old = $unit->toArray();
        $unit->update([
            'property_type_id' => $data['property_type_id'],
            'handover_date' => now()->toDateString(),
            'notes' => $data['notes'] ?? $unit->notes,
            'updated_by' => $request->user()->id,
        ]);
        $auditService->log('unit_property_converted', 'units', 'CONVERT_PROPERTY', $unit, $old, $unit->toArray());

        return $this->success($unit->refresh(), 'Properti berhasil dikonversi.');
    }

    private function validateUnit(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'id' => ['required', 'string', 'size:5', Rule::unique('units', 'id')->ignore($unit?->id, 'id')],
            'resident_id' => ['required', 'exists:residents,id'],
            'cluster_id' => ['required', 'exists:clusters,id'],
            'block' => ['required', 'string', 'max:5'],
            'lot_number' => ['required', 'string', 'max:10'],
            'property_type_id' => ['required', 'exists:property_types,id'],
            'building_area' => ['nullable', 'numeric', 'min:0'],
            'land_area' => ['nullable', 'numeric', 'min:0'],
            'handover_date' => ['nullable', 'date'],
            'occupancy_id' => ['required', 'exists:occupancy_statuses,id'],
            'status_id' => ['required', 'exists:resident_statuses,id'],
            'is_penalty_eligible' => ['sometimes', 'boolean'],
            'is_discount_eligible' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
