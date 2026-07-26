<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApprovalRequest;
use App\Models\Reversal;
use App\Services\ApprovalService;
use App\Services\ReversalService;
use Illuminate\Http\Request;

class ReversalController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Reversal::query()->with('receipt.unit.resident')
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, ApprovalService $approvalService)
    {
        $data = $request->validate([
            'receipt_number' => ['required', 'exists:receipts,number'],
            'reason' => ['required', 'string'],
        ]);

        $reversal = Reversal::query()->create([
            ...$data,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]);

        $reversal->load('receipt');

        $approvalService->openFor($reversal, ApprovalRequest::TYPE_REVERSAL, $request->user()->id, [
            'reason' => $data['reason'],
            'amount' => (float) ($reversal->receipt?->paymentTransaction?->total ?? 0),
        ]);

        return $this->success($reversal, 'Pengajuan pembatalan berhasil dibuat.', 201);
    }

    public function approve(Request $request, Reversal $reversal, ReversalService $service)
    {
        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:200']]);

        return $this->success($service->approve($reversal, $request->user()->id, $data['review_notes'] ?? null), 'Pembatalan berhasil disetujui.');
    }

    public function reject(Request $request, Reversal $reversal, ReversalService $service)
    {
        $data = $request->validate(['review_notes' => ['required', 'string', 'max:200']]);

        return $this->success($service->reject($reversal, $request->user()->id, $data['review_notes']), 'Pembatalan berhasil ditolak.');
    }
}
