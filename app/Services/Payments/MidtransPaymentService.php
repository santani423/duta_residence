<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MidtransPaymentService implements PaymentGatewayInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction
    {
        $baseUrl = config('payment.midtrans.is_production') ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';
        $response = Http::withBasicAuth((string) config('payment.midtrans.server_key'), '')
            ->post("{$baseUrl}/snap/v1/transactions", [
                'transaction_details' => [
                    'order_id' => $transaction->invoice_number,
                    'gross_amount' => (int) $transaction->total,
                ],
                'enabled_payments' => ['bank_transfer', 'gopay', 'qris', 'credit_card'],
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['payment_gateway' => ['Gagal membuat transaksi Midtrans.']]);
        }

        $payload = $response->json();

        $transaction->forceFill([
            'payment_provider' => 'midtrans',
            'provider_reference' => $payload['token'] ?? null,
            'payment_url' => $payload['redirect_url'] ?? null,
            'provider_payload' => app(\App\Services\AuditService::class)->sanitize($payload),
        ])->save();

        return $transaction->refresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        $orderId = (string) $request->input('order_id');
        $statusCode = (string) $request->input('status_code');
        $grossAmount = (string) $request->input('gross_amount');
        $serverKey = (string) config('payment.midtrans.server_key');
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($signature, (string) $request->input('signature_key'));
    }

    public function handleWebhook(Request $request): PaymentTransaction
    {
        if (! $this->verifyWebhook($request)) {
            abort(403, 'Invalid Midtrans signature.');
        }

        $transaction = PaymentTransaction::query()
            ->where('invoice_number', $request->input('order_id'))
            ->lockForUpdate()
            ->firstOrFail();

        $status = match ($request->input('transaction_status')) {
            'settlement', 'capture' => $request->input('fraud_status') === 'deny' ? 'failed' : 'paid',
            'pending' => 'pending',
            'deny' => 'failed',
            'cancel' => 'cancelled',
            'expire' => 'expired',
            'refund' => 'refunded',
            default => 'pending',
        };

        $transaction->forceFill([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : $transaction->paid_at,
            'provider_payload' => app(\App\Services\AuditService::class)->sanitize($request->all()),
        ])->save();

        return $transaction->refresh();
    }
}
