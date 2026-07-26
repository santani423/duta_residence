<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\ManagedFile;
use App\Models\Unit;
use App\Services\ApprovalService;
use App\Services\BillingAdjustmentService;
use App\Services\InstallmentPlanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApprovalRequestController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['requester', 'relatedCollector', 'relatedUnit', 'relatedResident', 'decider'];

    public function index(Request $request)
    {
        $query = ApprovalRequest::query()
            ->with(self::RELATIONS)
            ->when($request->query('type'), fn ($q, $value) => $q->where('type', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('related_collector_id'), fn ($q, $value) => $q->where('related_collector_id', $value))
            ->when($request->boolean('pending_only'), fn ($q) => $q->pending());

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(Request $request, ApprovalRequest $approvalRequest)
    {
        return $this->success($approvalRequest->load([...self::RELATIONS, 'requestable', 'documents']));
    }

    public function submitInstallmentPlan(Request $request, InstallmentPlanService $service, ApprovalService $approvalService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_id' => ['nullable', 'exists:billings,id'],
            'total_outstanding' => ['required', 'numeric', 'min:1'],
            'number_of_installments' => ['required', 'integer', 'min:2', 'max:24'],
            'frequency' => ['nullable', Rule::in(['weekly', 'monthly'])],
            'start_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $unit = Unit::query()->findOrFail($data['unit_id']);
        $plan = $service->submit($unit, $data, $request->user()->id);

        $approval = $approvalService->openFor($plan, ApprovalRequest::TYPE_INSTALLMENT_PLAN, $request->user()->id, [
            'reason' => $data['reason'],
            'amount' => (float) $data['total_outstanding'],
            'related_unit_id' => $unit->id,
            'related_resident_id' => $unit->resident_id,
        ]);

        return $this->success($approval->load(self::RELATIONS), 'Pengajuan rencana cicilan berhasil dibuat.', 201);
    }

    public function submitBillingAdjustment(Request $request, BillingAdjustmentService $service, ApprovalService $approvalService)
    {
        $data = $request->validate([
            'billing_id' => ['required', 'exists:billings,id'],
            'adjustment_type' => ['required', Rule::in(['discount', 'penalty', 'principal_correction'])],
            'new_value' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $billing = Billing::query()->with('unit')->findOrFail($data['billing_id']);
        $adjustment = $service->submit($billing, $data['adjustment_type'], (float) $data['new_value'], $data['reason'], $request->user()->id);

        $approval = $approvalService->openFor($adjustment, ApprovalRequest::TYPE_BILLING_ADJUSTMENT, $request->user()->id, [
            'reason' => $data['reason'],
            'amount' => (float) $data['new_value'],
            'related_unit_id' => $billing->unit_id,
        ]);

        return $this->success($approval->load(self::RELATIONS), 'Pengajuan penyesuaian tagihan berhasil dibuat.', 201);
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest, ApprovalService $service)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        return $this->success($service->approve($approvalRequest, $request->user()->id, $data['notes'] ?? null)->load(self::RELATIONS), 'Pengajuan berhasil disetujui.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest, ApprovalService $service)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);

        return $this->success($service->reject($approvalRequest, $request->user()->id, $data['notes'])->load(self::RELATIONS), 'Pengajuan berhasil ditolak.');
    }

    public function uploadDocument(Request $request, ApprovalRequest $approvalRequest)
    {
        $data = $request->validate(['file' => ['required', 'file', 'max:10240']]);

        $stored = $data['file']->store('approval-documents', 'public');
        $document = ManagedFile::query()->create([
            'original_filename' => $data['file']->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $data['file']->getMimeType(),
            'extension' => $data['file']->getClientOriginalExtension(),
            'size' => $data['file']->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => ApprovalRequest::class,
            'entity_id' => $approvalRequest->id,
        ]);

        return $this->success($document, 'Dokumen pendukung berhasil diunggah.', 201);
    }
}
