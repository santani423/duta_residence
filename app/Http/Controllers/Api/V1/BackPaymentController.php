<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Models\Customer;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class BackPaymentController extends Controller
{
    use ApiResponse;

    public function process(Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'loket_code' => ['nullable', 'string', 'max:20'],
            'cashier_name' => ['nullable', 'string', 'max:50'],
        ]);

        $receipt = $service->process(Customer::findOrFail($data['customer_id']), $data['billing_ids'], $data, $request->user()->id);

        return $this->success($receipt, 'Pelunasan mundur berhasil diproses.', 201);
    }
}
