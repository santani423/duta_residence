<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\UnitOccupant;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitOccupantController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Resident $resident)
    {
        $unitIds = $resident->units()->pluck('id')->all();

        $query = UnitOccupant::query()
            ->with('unit')
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('type'), fn ($q, $value) => $q->where('type', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $this->validateOccupant($request);
        $data['unit_id'] = $unit->id;
        $data['created_by'] = $request->user()->id;

        $occupant = UnitOccupant::query()->create($data);
        $auditService->log('unit_occupant_created', 'occupants', 'CREATE', $occupant, [], $occupant->toArray());

        return $this->success($occupant->load('unit'), 'Data berhasil ditambahkan.', 201);
    }

    public function update(Request $request, UnitOccupant $occupant, AuditService $auditService)
    {
        $data = $this->validateOccupant($request);
        $old = $occupant->toArray();
        $occupant->update($data);
        $auditService->log('unit_occupant_updated', 'occupants', 'UPDATE', $occupant, $old, $occupant->refresh()->toArray());

        return $this->success($occupant->load('unit'), 'Data berhasil diperbarui.');
    }

    public function destroy(UnitOccupant $occupant, AuditService $auditService)
    {
        $old = $occupant->toArray();
        $occupant->delete();
        $auditService->log('unit_occupant_deleted', 'occupants', 'DELETE', $occupant, $old, []);

        return $this->success(null, 'Data berhasil dihapus.');
    }

    private function validateOccupant(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['family', 'staff'])],
            'name' => ['required', 'string', 'max:100'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'identity_number' => ['nullable', 'string', 'max:30'],
            'start_date' => ['nullable', 'date'],
            'access_valid_until' => ['nullable', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:100'],
            'access_status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }
}
