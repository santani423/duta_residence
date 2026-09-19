<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApprovalRequest;
use App\Models\PaymentScheme;
use App\Models\Unit;
use App\Services\ApprovalService;
use App\Services\PaymentSchemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentSchemeController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['unit.cluster', 'unit.resident', 'submitter', 'adjuster', 'decider', 'items.billing', 'approvalRequest'];

    public function index(Request $request, PaymentSchemeService $service)
    {
        $query = PaymentScheme::query()
            ->with(self::RELATIONS)
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('unit_id', 'like', "%{$value}%")
                ->orWhereHas('unit.resident', fn ($r) => $r->where('name', 'like', "%{$value}%"))));

        $paginator = $query->latest()->paginate($request->integer('per_page', 15));
        $service->annotate($paginator->items(), $request->user());

        return $this->paginated($paginator);
    }

    public function show(Request $request, PaymentScheme $paymentScheme, PaymentSchemeService $service)
    {
        return $this->success($this->annotated($paymentScheme, $request, $service));
    }

    /** Dry run against the latest billing condition; nothing is stored. */
    public function preview(Request $request, PaymentSchemeService $service)
    {
        $data = $this->validated($request, false);

        return $this->success($service->calculate(Unit::query()->findOrFail($data['unit_id']), $data));
    }

    public function store(Request $request, PaymentSchemeService $service, ApprovalService $approvalService)
    {
        $data = $this->validated($request, true);
        $unit = Unit::query()->findOrFail($data['unit_id']);

        $scheme = DB::transaction(function () use ($service, $approvalService, $unit, $data, $request) {
            $scheme = $service->submit($unit, $data, $request->user()->id);

            $approvalService->openFor($scheme, ApprovalRequest::TYPE_PAYMENT_SCHEME, $request->user()->id, [
                'reason' => $data['reason'],
                'amount' => (float) $scheme->final_amount,
                'related_unit_id' => $unit->id,
                'related_resident_id' => $unit->resident_id,
                'before_value' => ['principal' => (float) $scheme->original_principal, 'penalty' => (float) $scheme->original_penalty],
                'after_value' => ['principal_discount' => (float) $scheme->principal_discount, 'penalty_reduction' => (float) $scheme->penalty_reduction, 'final_amount' => (float) $scheme->final_amount],
            ]);

            return $scheme;
        });

        return $this->success($this->annotated($scheme, $request, $service), 'Pengajuan skema pembayaran berhasil dibuat.', 201);
    }

    /** What Admin's edited discount/penalty terms would give; nothing is stored. */
    public function previewAdjustment(Request $request, PaymentScheme $paymentScheme, PaymentSchemeService $service)
    {
        return $this->success($service->previewAdjustment($paymentScheme, $this->adjustments($request)));
    }

    /** Approve, optionally with `adjustments` = Admin's edited final terms (see PaymentSchemeService::approve). */
    public function approve(Request $request, PaymentScheme $paymentScheme, ApprovalService $approvalService, PaymentSchemeService $service)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $options = $request->has('adjustments') ? ['adjustments' => $this->adjustments($request)] : [];
        $approvalService->approve($this->approvalFor($paymentScheme), $request->user()->id, $data['notes'] ?? null, $options);

        return $this->success($this->annotated($paymentScheme->refresh(), $request, $service), 'Skema pembayaran berhasil disetujui.');
    }

    public function reject(Request $request, PaymentScheme $paymentScheme, ApprovalService $approvalService, PaymentSchemeService $service)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $approvalService->reject($this->approvalFor($paymentScheme), $request->user()->id, $data['notes']);

        return $this->success($this->annotated($paymentScheme->refresh(), $request, $service), 'Skema pembayaran berhasil ditolak.');
    }

    private function annotated(PaymentScheme $scheme, Request $request, PaymentSchemeService $service): PaymentScheme
    {
        $scheme->load(self::RELATIONS);
        $service->annotate([$scheme], $request->user());

        return $scheme;
    }

    /** Approve/reject always go through the Approval Center request so both stay in sync. */
    private function approvalFor(PaymentScheme $scheme): ApprovalRequest
    {
        return $scheme->approvalRequest()->firstOrFail();
    }

    private function adjustments(Request $request): array
    {
        return $request->validate([
            'adjustments' => ['required', 'array'],
            'adjustments.discount_type' => ['nullable', Rule::in(['percentage', 'nominal'])],
            'adjustments.discount_value' => ['nullable', 'numeric', 'min:0'],
            'adjustments.penalty_reductions' => ['nullable', 'array'],
            'adjustments.penalty_reductions.*' => ['nullable', 'numeric', 'min:0'],
            'adjustments.rejected_billing_ids' => ['nullable', 'array'],
            'adjustments.rejected_billing_ids.*' => ['integer'],
        ])['adjustments'];
    }

    private function validated(Request $request, bool $withReason): array
    {
        return $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'discount_type' => ['nullable', Rule::in(['percentage', 'nominal'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'penalty_reduction' => ['nullable', 'numeric', 'min:0'],
            'penalty_reductions' => ['nullable', 'array'],
            'penalty_reductions.*' => ['nullable', 'numeric', 'min:0'],
            'reason' => [$withReason ? 'required' : 'nullable', 'string', 'max:500'],
        ]);
    }
}
