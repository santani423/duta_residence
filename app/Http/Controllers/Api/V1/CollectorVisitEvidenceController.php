<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Services\AuditService;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorVisitEvidenceController extends Controller
{
    use ApiResponse;

    /**
     * Bukti kunjungan hanya boleh dibaca bila unit kunjungan dalam cakupan user: collector →
     * unit yang ditugaskan kepadanya, supervisor → unit di clusternya, full scope → semua.
     */
    public function index(Request $request, CollectorVisit $visit, CollectionScopeService $scope)
    {
        $scope->assertUnitInScope($request->user(), $visit->unit_id);

        return $this->success($visit->evidence()->with('uploader')->latest()->get());
    }

    public function store(Request $request, CollectorVisit $visit, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $visit->unit_id);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['photo', 'document', 'gps', 'signature'])],
            'file' => ['required_unless:type,gps', 'nullable', 'file', 'max:10240'],
            'latitude' => ['required_if:type,gps', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:type,gps', 'nullable', 'numeric', 'between:-180,180'],
            'captured_at' => ['nullable', 'date'],
        ]);

        $evidence = CollectorVisitEvidence::query()->create([
            'visit_id' => $visit->id,
            'type' => $data['type'],
            'file_path' => isset($data['file']) ? $data['file']->store('collector-visit-evidence', 'public') : null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'captured_at' => $data['captured_at'] ?? now(),
            'uploaded_by' => $request->user()->id,
        ]);

        $auditService->log('collector_visit_evidence_uploaded', 'collector-evidence', 'CREATE', $evidence, [], $evidence->toArray());

        return $this->success($evidence->load('uploader'), 'Bukti kunjungan berhasil diunggah.', 201);
    }

    public function destroy(Request $request, CollectorVisitEvidence $evidence, AuditService $auditService, CollectionScopeService $scope)
    {
        $visit = CollectorVisit::query()->withTrashed()->find($evidence->visit_id);
        abort_unless($visit, 404, 'Kunjungan untuk bukti ini tidak ditemukan.');
        $scope->assertUnitInScope($request->user(), $visit->unit_id);

        $old = $evidence->toArray();
        $evidence->delete();
        $auditService->log('collector_visit_evidence_deleted', 'collector-evidence', 'DELETE', $evidence, $old, []);

        return $this->success(null, 'Bukti kunjungan berhasil dihapus.');
    }
}
