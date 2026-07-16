<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\PenaltyWaiver;
use App\Services\PenaltyWaiverService;
use Illuminate\Http\Request;

class PenaltyWaiverController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = PenaltyWaiver::query()
            ->with(['billing.unit', 'submitter', 'approver'])
            ->when($request->query('billing_id'), fn ($q, $value) => $q->where('billing_id', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, PenaltyWaiverService $service)
    {
        $data = $request->validate([
            'billing_id' => ['required', 'exists:billings,id'],
            'waived_penalty_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $billing = Billing::query()->with('unit')->findOrFail($data['billing_id']);
        $waiver = $service->submit($billing, (float) $data['waived_penalty_amount'], $data['reason'], $request->user()->id);

        return $this->success($waiver->load('billing'), 'Pengajuan keringanan denda berhasil dibuat.', 201);
    }

    public function approve(Request $request, PenaltyWaiver $penaltyWaiver, PenaltyWaiverService $service)
    {
        $data = $request->validate(['review_notes' => ['nullable', 'string', 'max:200']]);

        return $this->success($service->approve($penaltyWaiver, $request->user()->id, $data['review_notes'] ?? null), 'Keringanan denda berhasil disetujui.');
    }

    public function reject(Request $request, PenaltyWaiver $penaltyWaiver, PenaltyWaiverService $service)
    {
        $data = $request->validate(['review_notes' => ['required', 'string', 'max:200']]);

        return $this->success($service->reject($penaltyWaiver, $request->user()->id, $data['review_notes']), 'Pengajuan keringanan denda berhasil ditolak.');
    }
}
