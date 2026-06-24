<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class XenditPaymentService implements PaymentGatewayInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction
    {
        $setting = PaymentGatewaySetting::current();

        $response = Http::withBasicAuth((string) config('payment.xendit.secret_key'), '')
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $transaction->invoice_number,
                'amount' => (float) $transaction->total,
                'currency' => $transaction->currency,
                'description' => "Grand Duta {$transaction->invoice_number}",
                'invoice_duration' => $setting->payment_timeout_minutes * 60,
                'success_redirect_url' => config('app.frontend_url'),
                'failure_redirect_url' => config('app.frontend_url'),
            ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['payment_gateway' => ['Gagal membuat invoice Xendit.']]);
        }

        $payload = $response->json();

        $transaction->forceFill([
            'payment_provider' => 'xendit',
            'provider_reference' => $payload['id'] ?? null,
            'payment_url' => $payload['invoice_url'] ?? null,
            'expired_at' => isset($payload['expiry_date']) ? Carbon::parse($payload['expiry_date']) : null,
            'provider_payload' => app(\App\Services\AuditService::class)->sanitize($payload),
        ])->save();

        return $transaction->refresh();
    }

    public function verifyWebhook(Request $request): bool
    {
        return hash_equals((string) config('payment.xendit.callback_token'), (string) $request->header('x-callback-token'));
    }

    public function handleWebhook(Request $request): PaymentTransaction
    {
        if (! $this->verifyWebhook($request)) {
            abort(403, 'Invalid Xendit callback token.');
        }

        $payload = $request->all();
        $transaction = PaymentTransaction::query()
            ->where('invoice_number', $payload['external_id'] ?? null)
            ->orWhere('provider_reference', $payload['id'] ?? null)
            ->lockForUpdate()
            ->firstOrFail();

        $status = match ($payload['status'] ?? null) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            default => 'pending',
        };

        $transaction->forceFill([
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : $transaction->paid_at,
            'provider_payload' => app(\App\Services\AuditService::class)->sanitize($payload),
        ])->save();

        return $transaction->refresh();
    }
}
