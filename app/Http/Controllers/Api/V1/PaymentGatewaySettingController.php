<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PaymentGatewaySetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentGatewaySettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        $setting = PaymentGatewaySetting::current();

        return $this->success([
            ...$setting->toArray(),
            'public_config' => $setting->publicConfig(),
        ]);
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'active_gateway' => ['required', Rule::in(['manual', 'xendit', 'midtrans'])],
            'enabled_gateways' => ['nullable', 'array', 'min:1'],
            'enabled_gateways.*' => [Rule::in(['manual', 'xendit', 'midtrans'])],
            'is_active' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(['sandbox', 'production'])],
            'currency' => ['required', 'string', 'size:3'],
            'admin_fee' => ['required', 'numeric', 'min:0'],
            'payment_timeout_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'manual_bank_name' => ['nullable', 'string', 'max:255'],
            'manual_account_number' => ['nullable', 'string', 'max:255'],
            'manual_account_name' => ['nullable', 'string', 'max:255'],
            'manual_instructions' => ['nullable', 'string'],
            'proof_max_size_kb' => ['required', 'integer', 'min:256', 'max:20480'],
            'proof_allowed_extensions' => ['nullable', 'array', 'min:1'],
            'proof_allowed_extensions.*' => [Rule::in(['jpg', 'jpeg', 'png', 'pdf'])],
            'xendit_public_key' => ['nullable', 'string', 'max:255'],
            'midtrans_client_key' => ['nullable', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url', 'max:255'],
            'webhook_notes' => ['nullable', 'string'],
        ]);

        $data['enabled_gateways'] = collect($data['enabled_gateways'] ?? [$data['active_gateway']])
            ->push($data['active_gateway'])
            ->unique()
            ->values()
            ->all();
        $data['proof_allowed_extensions'] = $data['proof_allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf'];
        $data['updated_by'] = $request->user()->id;

        $setting = PaymentGatewaySetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('payment_gateway_settings_updated', 'payment-settings', 'UPDATE', $setting, $old, $setting->toArray());

        return $this->success([
            ...$setting->refresh()->toArray(),
            'public_config' => $setting->publicConfig(),
        ], 'Pengaturan payment gateway berhasil disimpan.');
    }

    public function test()
    {
        $setting = PaymentGatewaySetting::current();
        $config = $setting->publicConfig();
        $gateway = $setting->active_gateway;

        return $this->success([
            'gateway' => $gateway,
            'mode' => $setting->mode,
            'ready' => (bool) ($config['credential_status'][$gateway] ?? false),
            'credential_status' => $config['credential_status'],
        ], 'Status konfigurasi gateway berhasil diperiksa.');
    }
}
