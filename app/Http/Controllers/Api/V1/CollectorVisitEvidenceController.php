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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CollectorVisitEvidenceController extends Controller
{
    use ApiResponse;

    /** Tipe yang dapat menjalankan skrip bila dibuka inline; selalu dikirim sebagai unduhan biner. */
    private const ACTIVE_MIME_TYPES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        'text/javascript', 'application/javascript',
    ];

    /**
     * Bukti kunjungan hanya boleh dibaca bila unit kunjungan dalam cakupan user: collector →
     * unit yang ditugaskan kepadanya, supervisor → unit di clusternya, full scope → semua.
     */
    public function index(Request $request, CollectorVisit $visit, CollectionScopeService $scope)
    {
        $scope->assertUnitInScope($request->user(), $visit->unit_id);

        return $this->success($visit->evidence()->with('uploader')->latest()->get());
    }

    /**
     * Tanda tangan penghuni menyelesaikan kunjungan "Selesai" yang masih menunggu tanda tangan
     * (lifecycle → completed) dalam satu transaksi dengan baris buktinya. Tanda tangan ulang pada
     * kunjungan yang sudah selesai hanya menambah baris bukti.
     */
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
        $isSignature = $data['type'] === CollectorVisitEvidence::TYPE_SIGNATURE;
        $filePath = isset($data['file']) ? $data['file']->store('collector-visit-evidence', 'public') : null;
        // Disk public tidak melempar exception (throw => false): gagal tulis = false. Tanpa berkas
        // tidak ada baris bukti, dan kunjungan tetap menunggu tanda tangan supaya HP bisa mencoba lagi.
        if ($filePath === false) {
            return $this->error('Berkas bukti gagal disimpan. Silakan coba lagi.', 500);
        }

        // $before = snapshot kunjungan sebelum diselesaikan; null bila unggahan ini tidak menyelesaikannya.
        [$evidence, $visit, $before] = DB::transaction(function () use ($request, $visit, $data, $isSignature, $filePath) {
            // Dikunci supaya unggahan ganda (retry dari HP) tidak menyelesaikan kunjungan dua kali.
            $locked = CollectorVisit::query()->lockForUpdate()->findOrFail($visit->id);

            $evidence = CollectorVisitEvidence::query()->create([
                'visit_id' => $locked->id,
                'type' => $data['type'],
                'file_path' => $filePath,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'captured_at' => $data['captured_at'] ?? now(),
                'uploaded_by' => $request->user()->id,
            ]);

            $completes = $isSignature
                && $locked->status === CollectorVisit::STATUS_COMPLETED
                && $locked->lifecycle !== CollectorVisit::LIFECYCLE_COMPLETED;
            $before = $completes ? $locked->toArray() : null;

            if ($completes) {
                $locked->update([
                    'lifecycle' => CollectorVisit::LIFECYCLE_COMPLETED,
                    'finished_at' => now(),
                    'updated_by' => $request->user()->id,
                ]);
            }

            return [$evidence, $locked, $before];
        });

        $auditService->log('collector_visit_evidence_uploaded', 'collector-evidence', 'CREATE', $evidence, [], $evidence->toArray());
        if ($before !== null) {
            $auditService->log('collector_visit_completed', 'visits', 'UPDATE', $visit, $before, $visit->refresh()->toArray());
        }

        $visit->load(['unit.cluster', 'collector'])->loadSignatureState();
        $message = $isSignature && $visit->lifecycle === CollectorVisit::LIFECYCLE_COMPLETED
            ? 'Tanda tangan tersimpan. Kunjungan selesai.'
            : 'Bukti kunjungan berhasil diunggah.';

        return $this->success($evidence->load('uploader')->setRelation('visit', $visit), $message, 201);
    }

    /**
     * Stream berkas bukti (foto/dokumen/tanda tangan) inline untuk klien ber-bearer token, dengan
     * cakupan unit yang sama seperti index(). Bukti yang sudah dihapus tidak dapat diakses.
     */
    public function file(Request $request, CollectorVisitEvidence $evidence, CollectionScopeService $scope)
    {
        $scope->assertUnitInScope($request->user(), $this->visitOf($evidence)->unit_id);

        $disk = Storage::disk('public');
        abort_unless($evidence->file_path && $disk->exists($evidence->file_path), 404, 'Berkas bukti tidak ditemukan.');

        $mimeType = $disk->mimeType($evidence->file_path) ?: 'application/octet-stream';
        $active = in_array($mimeType, self::ACTIVE_MIME_TYPES, true);

        return $disk->response(
            $evidence->file_path,
            basename($evidence->file_path),
            ['Content-Type' => $active ? 'application/octet-stream' : $mimeType, 'X-Content-Type-Options' => 'nosniff'],
            $active ? 'attachment' : 'inline',
        );
    }

    public function destroy(Request $request, CollectorVisitEvidence $evidence, AuditService $auditService, CollectionScopeService $scope)
    {
        $scope->assertUnitInScope($request->user(), $this->visitOf($evidence)->unit_id);

        $old = $evidence->toArray();
        $evidence->delete();
        $auditService->log('collector_visit_evidence_deleted', 'collector-evidence', 'DELETE', $evidence, $old, []);

        return $this->success(null, 'Bukti kunjungan berhasil dihapus.');
    }

    /** Kunjungan pemilik bukti, termasuk yang sudah dihapus (soft delete). */
    private function visitOf(CollectorVisitEvidence $evidence): CollectorVisit
    {
        $visit = CollectorVisit::query()->withTrashed()->find($evidence->visit_id);
        abort_unless($visit, 404, 'Kunjungan untuk bukti ini tidak ditemukan.');

        return $visit;
    }
}
