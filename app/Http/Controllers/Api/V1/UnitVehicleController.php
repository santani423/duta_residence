<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\UnitVehicle;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitVehicleController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Resident $resident)
    {
        $unitIds = $resident->units()->pluck('id')->all();

        $query = UnitVehicle::query()->with('unit')->whereIn('unit_id', $unitIds);

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $this->validateVehicle($request);
        $data['unit_id'] = $unit->id;
        $data['created_by'] = $request->user()->id;

        $vehicle = UnitVehicle::query()->create($data);
        $auditService->log('unit_vehicle_created', 'vehicles', 'CREATE', $vehicle, [], $vehicle->toArray());

        return $this->success($vehicle->load('unit'), 'Kendaraan berhasil ditambahkan.', 201);
    }

    public function update(Request $request, UnitVehicle $vehicle, AuditService $auditService)
    {
        $data = $this->validateVehicle($request);
        $old = $vehicle->toArray();
        $vehicle->update($data);
        $auditService->log('unit_vehicle_updated', 'vehicles', 'UPDATE', $vehicle, $old, $vehicle->refresh()->toArray());

        return $this->success($vehicle->load('unit'), 'Kendaraan berhasil diperbarui.');
    }

    public function destroy(UnitVehicle $vehicle, AuditService $auditService)
    {
        $old = $vehicle->toArray();
        $vehicle->delete();
        $auditService->log('unit_vehicle_deleted', 'vehicles', 'DELETE', $vehicle, $old, []);

        return $this->success(null, 'Kendaraan berhasil dihapus.');
    }

    private function validateVehicle(Request $request): array
    {
        return $request->validate([
            'vehicle_type' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:30'],
            'plate_number' => ['required', 'string', 'max:20'],
            'sticker_number' => ['nullable', 'string', 'max:30'],
            'sticker_valid_until' => ['nullable', 'date'],
            'owner_name' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
