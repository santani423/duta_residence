<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManualPaymentService implements PaymentGatewayInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction
    {
        $setting = PaymentGatewaySetting::current();

        $transaction->forceFill([
            'payment_provider' => 'manual',
            'status' => 'pending',
            'provider_payload' => [
                'bank_name' => $setting->manual_bank_name,
                'account_number' => $setting->manual_account_number,
                'account_name' => $setting->manual_account_name,
                'instructions' => $setting->manual_instructions,
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
