<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BillingService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Billing::query()
            ->with(['unit.cluster', 'unit.customer', 'status', 'approver'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('cluster_id', $value)));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function prepareMonthly(Request $request, BillingService $service, AuditService $auditService)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $billings = $service->prepareMonthly($data['year'], $data['month'], $request->user()->id);
        $auditService->log('monthly_billing_prepared', 'billings', 'PREPARE', null, [], $data);

        return $this->success(['count' => $billings->count(), 'billings' => $billings->values()], 'Tagihan bulanan berhasil disiapkan.', 201);
    }

    public function prepareSpecial(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $billing = $service->prepareSpecial(Unit::findOrFail($data['unit_id']), $data['year'], $data['month'], $data['amount'], $request->user()->id);

        return $this->success($billing->load('unit'), 'Tagihan khusus berhasil dibuat.', 201);
    }

    public function prepareBack(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'periods.*.month' => ['required', 'integer', 'between:1,12'],
            'periods.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $billings = collect($data['periods'])->map(fn ($period) => tap(
            $service->prepareSpecial($unit, $period['year'], $period['month'], $period['amount'], $request->user()->id),
            fn (Billing $billing) => $billing->update(['billing_type' => 'back'])
        ));

        return $this->success($billings->values(), 'Tagihan mundur berhasil dibuat.', 201);
    }

    public function pendingApproval(Request $request)
    {
        $query = Billing::query()->with(['unit.cluster', 'unit.customer'])->whereNull('approved_at')->where('status_id', '01');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function approve(Request $request, Billing $billing, BillingService $service)
    {
        $data = $request->validate(['approval_notes' => ['nullable', 'string', 'max:200']]);

        return $this->success($service->approve($billing, $request->user()->id, $data['approval_notes'] ?? null), 'Tagihan berhasil disetujui.');
    }

    public function approveBatch(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'approval_notes' => ['nullable', 'string', 'max:200'],
        ]);

        $billings = Billing::query()->whereIn('id', $data['billing_ids'])->get()
            ->map(fn (Billing $billing) => $service->approve($billing, $request->user()->id, $data['approval_notes'] ?? null));

        return $this->success($billings->values(), 'Tagihan batch berhasil disetujui.');
    }
}
