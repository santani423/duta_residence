<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Resident;
use App\Models\ResidentDocument;
use App\Models\Unit;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentDocumentController extends Controller
{
    use ApiResponse;

    public function indexForResident(Request $request, Resident $resident)
    {
        $unitIds = $resident->units()->pluck('id')->all();

        $query = ResidentDocument::query()
            ->with(['verifier', 'creator'])
            ->where(fn ($q) => $q->where('documentable_type', Resident::class)->where('documentable_id', $resident->id))
            ->orWhere(fn ($q) => $q->where('documentable_type', Unit::class)->whereIn('documentable_id', $unitIds))
            ->when($request->query('document_type'), fn ($q, $value) => $q->where('document_type', $value))
            ->when($request->query('verification_status'), fn ($q, $value) => $q->where('verification_status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function indexForUnit(Request $request, Unit $unit)
    {
        $query = ResidentDocument::query()
            ->with(['verifier', 'creator'])
            ->where('documentable_type', Unit::class)
            ->where('documentable_id', $unit->id);

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeForResident(Request $request, Resident $resident, AuditService $auditService)
    {
        return $this->storeDocument($request, $resident, $auditService);
    }

    public function storeForUnit(Request $request, Unit $unit, AuditService $auditService)
    {
        return $this->storeDocument($request, $unit, $auditService);
    }

    private function storeDocument(Request $request, Resident|Unit $documentable, AuditService $auditService)
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'document_date' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $data['file'];
        $stored = $file->store('resident-documents', 'public');

        $document = ResidentDocument::query()->create([
            ...collect($data)->except('file')->all(),
            'documentable_type' => $documentable::class,
            'documentable_id' => $documentable->id,
            'file_path' => $stored,
            'original_filename' => $file->getClientOriginalName(),
            'verification_status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        $auditService->log('resident_document_uploaded', 'resident-documents', 'CREATE', $document, [], $document->toArray());

        return $this->success($document->load(['verifier', 'creator']), 'Dokumen berhasil diunggah.', 201);
    }

    public function update(Request $request, ResidentDocument $document, AuditService $auditService)
    {
        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:60'],
            'document_date' => ['nullable', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
            'verification_status' => ['sometimes', Rule::in(['pending', 'verified', 'rejected'])],
        ]);

        if (array_key_exists('verification_status', $data)) {
            abort_unless($request->user()->can('resident-documents.verify'), 403, 'Anda tidak memiliki akses verifikasi dokumen.');
            $data['verified_by'] = $request->user()->id;
            $data['verified_at'] = now();
        }
        $data['updated_by'] = $request->user()->id;

        $old = $document->toArray();
        $document->update($data);
        $auditService->log('resident_document_updated', 'resident-documents', 'UPDATE', $document, $old, $document->refresh()->toArray());

        return $this->success($document->load(['verifier', 'creator']), 'Dokumen berhasil diperbarui.');
    }

    public function destroy(ResidentDocument $document, AuditService $auditService)
    {
        $old = $document->toArray();
        $document->delete();
        $auditService->log('resident_document_deleted', 'resident-documents', 'DELETE', $document, $old, []);

        return $this->success(null, 'Dokumen berhasil dihapus.');
    }
}
