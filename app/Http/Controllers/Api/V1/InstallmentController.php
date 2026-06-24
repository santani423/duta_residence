<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\Installment;
use App\Services\AuditService;
use Illuminate\Http\Request;

class InstallmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Installment::query()
            ->with('customer.cluster')
            ->when($request->query('customer_id'), fn ($q, $value) => $q->where('customer_id', $value));

        return $this->paginated($query->latest('payment_date')->paginate($request->integer('per_page', 15)));
    }

    public function byCustomer(Customer $customer)
    {
        return $this->success($customer->installments()->latest('payment_date')->get());
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
            'allocated_to' => ['nullable', 'string', 'max:200'],
        ]);
        $data['created_by'] = $request->user()->id;

        $installment = Installment::query()->create($data);
        $auditService->log('installment_created', 'installments', 'CREATE', $installment, [], $installment->toArray());

        return $this->success($installment->load('customer'), 'Cicilan berhasil dicatat.', 201);
    }
}
