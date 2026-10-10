<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Models\Resident;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\ResidentDetailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorVisitController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Resident $resident, ResidentDetailService $service, CollectorAssignmentService $assignmentService)
    {
        $unitIds = $service->unitIds($resident, $request->query('unit_id'));

        if ($request->user()->hasRole('collector')) {
            $unitIds = array_values(array_intersect($unitIds, $assignmentService->unitIdsFor($request->user())));
        }

        $query = CollectorVisit::query()
            ->with(['unit.cluster', 'collector'])
            ->withSignatureState()
            ->withCount('evidence')
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest('visit_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, Unit $unit, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $unit->id);
        }

        $data = $request->validate([
            'collector_id' => ['nullable', 'exists:users,id'],
            'visit_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:100'],
            'result' => ['nullable', 'string'],
            'met_with' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'checkin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'checkin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(CollectorVisit::STATUSES)],
            'next_visit_date' => ['nullable', 'date'],
        ]);
        $data['unit_id'] = $unit->id;
        // A collector can never attribute a visit to a different account; only a
        // non-collector staff role (e.g. admin logging on a collector's behalf) may
        // set an explicit collector_id.
        $data['collector_id'] = $request->user()->hasRole('collector')
            ? $request->user()->id
            : ($data['collector_id'] ?? $request->user()->id);
        $data['created_by'] = $request->user()->id;

        // Kunjungan "Selesai" baru final setelah penghuni tanda tangan di HP collector: disimpan
        // sebagai in_progress (Menunggu tanda tangan). Hasil lain langsung final tanpa tanda tangan.
        $awaitingSignature = $data['status'] === CollectorVisit::STATUS_COMPLETED;
        $data += $awaitingSignature
            ? ['lifecycle' => CollectorVisit::LIFECYCLE_IN_PROGRESS, 'started_at' => $data['visit_date'], 'finished_at' => null]
            : ['lifecycle' => CollectorVisit::LIFECYCLE_COMPLETED, 'finished_at' => $data['visit_date']];

        $visit = CollectorVisit::query()->create($data);
        $auditService->log('collector_visit_created', 'visits', 'CREATE', $visit, [], $visit->toArray());

        return $this->success(
            $this->present($visit),
            $awaitingSignature ? 'Kunjungan tersimpan. Minta tanda tangan penghuni untuk menyelesaikannya.' : 'Kunjungan berhasil dicatat.',
            201,
        );
    }

    public function update(Request $request, CollectorVisit $visit, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $visit->unit_id);
        }

        $data = $request->validate([
            'visit_date' => ['required', 'date'],
            'purpose' => ['required', 'string', 'max:100'],
            'result' => ['nullable', 'string'],
            'met_with' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'checkin_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'checkin_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(CollectorVisit::STATUSES)],
            'next_visit_date' => ['nullable', 'date'],
        ]);

        if ($data['status'] === CollectorVisit::STATUS_COMPLETED) {
            $hasSignature = $visit->evidence()->where('type', CollectorVisitEvidence::TYPE_SIGNATURE)->exists();
            if (! $hasSignature) {
                return $this->error('Tanda tangan penghuni diperlukan sebelum kunjungan dapat diselesaikan.', 422);
            }
        }

        // Update selalu memfinalkan kunjungan: "Selesai" sudah dijamin bertanda tangan di atas,
        // hasil lain final tanpa tanda tangan.
        $finishing = $visit->lifecycle !== CollectorVisit::LIFECYCLE_COMPLETED;
        $data['lifecycle'] = CollectorVisit::LIFECYCLE_COMPLETED;
        $data['finished_at'] = $visit->finished_at ?? now();
        $data['updated_by'] = $request->user()->id;

        $old = $visit->toArray();
        $visit->update($data);
        $auditService->log('collector_visit_updated', 'visits', 'UPDATE', $visit, $old, $visit->refresh()->toArray());

        return $this->success($this->present($visit), $finishing ? 'Kunjungan berhasil diselesaikan.' : 'Kunjungan berhasil diperbarui.');
    }

    /** Bentuk respons satu kunjungan: unit.cluster, collector, has_signature, awaiting_signature. */
    private function present(CollectorVisit $visit): CollectorVisit
    {
        return $visit->load(['unit.cluster', 'collector'])->loadSignatureState();
    }
}
