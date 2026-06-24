<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customer;
use App\Models\Receipt;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function search(Request $request)
    {
        $data = $request->validate(['customer_id' => ['required', 'exists:customers,id']]);
        $customer = Customer::query()
            ->with(['cluster', 'billings' => fn ($q) => $q->unpaid()->approved()->orderBy('year')->orderBy('month')])
            ->findOrFail($data['customer_id']);

        return $this->success($customer);
    }

    public function preview(Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
        ]);

        return $this->success($service->preview(Customer::findOrFail($data['customer_id']), $data['billing_ids']));
    }

    public function process(Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payment_channel_id' => ['nullable', 'exists:payment_channels,id'],
            'loket_code' => ['nullable', 'string', 'max:20'],
            'cashier_name' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $receipt = $service->process(Customer::findOrFail($data['customer_id']), $data['billing_ids'], $data, $request->user()->id);

        return $this->success($receipt, 'Pembayaran berhasil diproses.', 201);
    }

    public function receipts(Request $request)
    {
        $query = Receipt::query()
            ->with('customer.cluster')
            ->when($request->query('date'), fn ($q, $value) => $q->whereDate('transaction_date', $value))
            ->when($request->query('customer_id'), fn ($q, $value) => $q->where('customer_id', $value));

        return $this->paginated($query->latest('transaction_date')->paginate($request->integer('per_page', 15)));
    }

    public function showReceipt(Receipt $receipt)
    {
        return $this->success($receipt->load(['customer.cluster', 'billings']));
    }
}
