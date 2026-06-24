<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManualPaymentService implements PaymentGatewayInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction
    {
        $transaction->forceFill([
            'payment_provider' => 'manual',
            'status' => 'waiting_verification',
            'provider_payload' => [
                'bank_name' => config('payment.manual.bank_name'),
                'account_number' => config('payment.manual.account_number'),
                'account_name' => config('payment.manual.account_name'),
            ],
        ])->save();

        return $transaction->refresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    public function handleWebhook(Request $request): PaymentTransaction
    {
        throw ValidationException::withMessages(['manual' => ['Pembayaran manual tidak menggunakan webhook.']]);
    }
}
