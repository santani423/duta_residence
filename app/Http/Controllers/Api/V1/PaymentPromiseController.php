<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PaymentPromise;
use App\Models\Resident;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\ResidentDetailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentPromiseController extends Controller
{
    use ApiResponse;

    public function index(Request $request, Resident $resident, ResidentDetailService $service)
    {
        $unitIds = $service->unitIds($resident, $request->query('unit_id'));

        $query = PaymentPromise::query()
            ->with(['unit.cluster', 'billing', 'creator'])
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest('promised_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, Unit $unit, AuditService $auditService)
    {
        $data = $this->validatePromise($request);
        $data['unit_id'] = $unit->id;
        $data['created_by'] = $request->user()->id;

        $promise = PaymentPromise::query()->create($data);
        $auditService->log('payment_promise_created', 'payment-promises', 'CREATE', $promise, [], $promise->toArray());

        return $this->success($promise->load(['unit.cluster', 'billing', 'creator']), 'Janji pembayaran berhasil dicatat.', 201);
    }

    public function update(Request $request, PaymentPromise $promise, AuditService $auditService)
    {
        $data = $this->validatePromise($request);
        $old = $promise->toArray();
        $promise->update($data);
        $auditService->log('payment_promise_updated', 'payment-promises', 'UPDATE', $promise, $old, $promise->refresh()->toArray());

        return $this->success($promise->load(['unit.cluster', 'billing', 'creator']), 'Janji pembayaran berhasil diperbarui.');
    }

    private function validatePromise(Request $request): array
    {
        return $request->validate([
            'billing_id' => ['nullable', 'exists:billings,id'],
            'promised_amount' => ['required', 'numeric', 'min:0'],
            'promised_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['pending', 'fulfilled', 'broken', 'rescheduled'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
