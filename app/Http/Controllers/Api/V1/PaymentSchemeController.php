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

    private const RELATIONS = ['unit.cluster', 'unit.resident', 'submitter', 'decider', 'items.billing', 'approvalRequest'];

    public function index(Request $request)
    {
        $query = PaymentScheme::query()
            ->with(self::RELATIONS)
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('unit_id', 'like', "%{$value}%")
                ->orWhereHas('unit.resident', fn ($r) => $r->where('name', 'like', "%{$value}%"))));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(PaymentScheme $paymentScheme)
    {
        return $this->success($paymentScheme->load(self::RELATIONS));
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

        return $this->success($scheme->load(self::RELATIONS), 'Pengajuan skema pembayaran berhasil dibuat.', 201);
    }

    public function approve(Request $request, PaymentScheme $paymentScheme, ApprovalService $approvalService)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);
        $approvalService->approve($this->approvalFor($paymentScheme), $request->user()->id, $data['notes'] ?? null);

        return $this->success($paymentScheme->refresh()->load(self::RELATIONS), 'Skema pembayaran berhasil disetujui.');
    }

    public function reject(Request $request, PaymentScheme $paymentScheme, ApprovalService $approvalService)
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']]);
        $approvalService->reject($this->approvalFor($paymentScheme), $request->user()->id, $data['notes']);

        return $this->success($paymentScheme->refresh()->load(self::RELATIONS), 'Skema pembayaran berhasil ditolak.');
    }

    /** Approve/reject always go through the Approval Center request so both stay in sync. */
    private function approvalFor(PaymentScheme $scheme): ApprovalRequest
    {
        return $scheme->approvalRequest()->firstOrFail();
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
            'reason' => [$withReason ? 'required' : 'nullable', 'string', 'max:500'],
        ]);
    }
}
