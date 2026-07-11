<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorVisit;
use App\Models\Resident;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\ResidentDetailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorVisitController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Resident $resident, ResidentDetailService $service)
    {
        $unitIds = $service->unitIds($resident, $request->query('unit_id'));

        $query = CollectorVisit::query()
            ->with(['unit.cluster', 'collector'])
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest('visit_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $request->validate([
            'collector_id' => ['nullable', 'exists:users,id'],
            'visit_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:100'],
            'result' => ['nullable', 'string'],
            'met_with' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'checkin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'checkin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(['completed', 'no_answer', 'refused', 'rescheduled'])],
            'next_visit_date' => ['nullable', 'date'],
        ]);
        $data['unit_id'] = $unit->id;
        $data['collector_id'] = $data['collector_id'] ?? $request->user()->id;
        $data['created_by'] = $request->user()->id;

        $visit = CollectorVisit::query()->create($data);
        $auditService->log('collector_visit_created', 'visits', 'CREATE', $visit, [], $visit->toArray());

        return $this->success($visit->load(['unit.cluster', 'collector']), 'Kunjungan berhasil dicatat.', 201);
    }

    public function update(Request $request, CollectorVisit $visit, AuditService $auditService)
    {
        $data = $request->validate([
            'visit_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:100'],
            'result' => ['nullable', 'string'],
            'met_with' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'checkin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'checkin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(['completed', 'no_answer', 'refused', 'rescheduled'])],
            'next_visit_date' => ['nullable', 'date'],
        ]);
        $old = $visit->toArray();
        $visit->update($data);
        $auditService->log('collector_visit_updated', 'visits', 'UPDATE', $visit, $old, $visit->refresh()->toArray());

        return $this->success($visit->load(['unit.cluster', 'collector']), 'Kunjungan berhasil diperbarui.');
    }
}
