<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectionLetter;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use App\Support\Pagination;
use App\Support\UnitFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CollectionLetterController extends Controller
{
    use ApiResponse;

    private const TITLES = [
        'reminder' => 'Surat Pengingat Pembayaran',
        'warning' => 'Surat Peringatan Tunggakan',
        'final_notice' => 'Surat Peringatan Terakhir',
    ];

    /**
     * Daftar surat dibatasi ke unit dalam cakupan user: collector → unit yang ditugaskan,
     * supervisor → unit di clusternya, full scope → semua.
     */
    public function index(Request $request, CollectionScopeService $scope)
    {
        $query = CollectionLetter::query()
            ->with(['unit.cluster', 'resident', 'billing', 'generatedBy'])
            ->tap(fn ($q) => $scope->constrainUnits($q, $request->user(), 'collection_letters.unit_id'))
            ->tap(fn ($q) => UnitFilters::apply($q, $request, except: ['resident_id']))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->where('resident_id', $value))
            ->when($request->query('letter_type'), fn ($q, $value) => $q->where('letter_type', $value));

        return $this->paginated($query->latest('generated_at')->paginate(Pagination::perPage($request)));
    }

    public function store(Request $request, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_id' => ['nullable', 'exists:billings,id'],
            'letter_type' => ['required', Rule::in(array_keys(self::TITLES))],
            'content' => ['required', 'string'],
        ]);

        $unit = Unit::query()->with(['cluster', 'resident'])->findOrFail($data['unit_id']);

        if (! $unit->resident_id) {
            return $this->error('Unit belum memiliki penghuni terdaftar, tidak dapat membuat surat penagihan.', 422);
        }

        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $unit->id);
        }

        $letter = CollectionLetter::query()->create([
            ...$data,
            'resident_id' => $unit->resident_id,
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);
        $letter->load(['unit.cluster', 'resident', 'billing']);

        $pdf = Pdf::loadHTML(view('pdf.collection-letter', [
            'letter' => $letter,
            'letterTitle' => self::TITLES[$letter->letter_type],
        ])->render());

        $path = "collection-letters/{$letter->id}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        $letter->update(['pdf_path' => $path]);

        $auditService->log('collection_letter_generated', 'collection-letters', 'CREATE', $letter, [], $letter->toArray());

        return $this->success($letter->load('generatedBy'), 'Surat penagihan berhasil dibuat.', 201);
    }

    public function download(Request $request, CollectionLetter $collectionLetter, CollectionScopeService $scope)
    {
        $scope->assertUnitInScope($request->user(), $collectionLetter->unit_id);
        abort_unless($collectionLetter->pdf_path && Storage::disk('public')->exists($collectionLetter->pdf_path), 404, 'Berkas surat tidak ditemukan.');

        return Storage::disk('public')->download($collectionLetter->pdf_path, "Surat-Penagihan-{$collectionLetter->id}.pdf");
    }
}
