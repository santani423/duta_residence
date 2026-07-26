<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectionLetter;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
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

    public function index(Request $request)
    {
        $query = CollectionLetter::query()
            ->with(['unit.cluster', 'resident', 'billing', 'generatedBy'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->where('resident_id', $value))
            ->when($request->query('letter_type'), fn ($q, $value) => $q->where('letter_type', $value));

        return $this->paginated($query->latest('generated_at')->paginate($request->integer('per_page', 15)));
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

    public function download(CollectionLetter $collectionLetter)
    {
        abort_unless($collectionLetter->pdf_path && Storage::disk('public')->exists($collectionLetter->pdf_path), 404, 'Berkas surat tidak ditemukan.');

        return Storage::disk('public')->download($collectionLetter->pdf_path, "Surat-Penagihan-{$collectionLetter->id}.pdf");
    }
}
