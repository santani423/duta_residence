<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewaySetting extends Model
{
    protected $fillable = [
        'active_gateway', 'enabled_gateways', 'is_active', 'mode', 'currency',
        'admin_fee', 'payment_timeout_minutes', 'manual_bank_name',
        'manual_account_number', 'manual_account_name', 'manual_instructions',
        'proof_max_size_kb', 'proof_allowed_extensions', 'xendit_public_key',
        'midtrans_client_key', 'callback_url', 'webhook_notes', 'updated_by',
    ];

    protected $casts = [
        'enabled_gateways' => 'array',
        'is_active' => 'boolean',
        'admin_fee' => 'decimal:2',
        'proof_allowed_extensions' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'active_gateway' => config('payment.gateway', 'manual'),
            'enabled_gateways' => [config('payment.gateway', 'manual')],
            'is_active' => true,
            'mode' => config('payment.midtrans.is_production') || config('payment.xendit.is_production') ? 'production' : 'sandbox',
            'currency' => config('payment.currency', 'IDR'),
            'admin_fee' => 0,
            'payment_timeout_minutes' => 1440,
            'manual_bank_name' => config('payment.manual.bank_name'),
            'manual_account_number' => config('payment.manual.account_number'),
            'manual_account_name' => config('payment.manual.account_name'),
            'manual_instructions' => 'Transfer sesuai total pembayaran lalu unggah bukti transfer.',
            'proof_max_size_kb' => config('grandduta.max_upload_size', 5120),
            'proof_allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'xendit_public_key' => config('payment.xendit.public_key'),
            'midtrans_client_key' => config('payment.midtrans.client_key'),
        ]);
    }

    public function availableGateways(): array
    {
        $gateways = $this->enabled_gateways ?: [$this->active_gateway];

        return collect($gateways)
            ->filter(fn ($gateway) => in_array($gateway, ['manual', 'xendit', 'midtrans'], true))
            ->when($this->is_active && $this->active_gateway, fn ($items) => $items->prepend($this->active_gateway))
            ->unique()
            ->values()
            ->all();
    }

    public function publicConfig(): array
    {
        $available = $this->availableGateways();

        return [
            'active_gateway' => $this->active_gateway,
            'available_methods' => $available,
            'is_active' => $this->is_active,
            'mode' => $this->mode,
            'currency' => $this->currency,
            'admin_fee' => (float) $this->admin_fee,
            'payment_timeout_minutes' => $this->payment_timeout_minutes,
            'manual_payment' => in_array('manual', $available, true) ? [
                'bank_name' => $this->manual_bank_name,
                'account_number' => $this->manual_account_number,
                'account_name' => $this->manual_account_name,
                'instructions' => $this->manual_instructions,
                'proof_max_size_kb' => $this->proof_max_size_kb,
                'proof_allowed_extensions' => $this->proof_allowed_extensions ?: ['jpg', 'jpeg', 'png', 'pdf'],
            ] : null,
            'public_keys' => [
                'xendit' => in_array('xendit', $available, true) ? $this->xendit_public_key : null,
                'midtrans' => in_array('midtrans', $available, true) ? $this->midtrans_client_key : null,
            ],
            'credential_status' => [
                'xendit' => filled(config('payment.xendit.secret_key')),
                'midtrans' => filled(config('payment.midtrans.server_key')),
                'manual' => filled($this->manual_account_number),
            ],
        ];
    }
}
