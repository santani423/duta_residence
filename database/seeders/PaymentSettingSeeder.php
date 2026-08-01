<?php

namespace Database\Seeders;

use App\Models\PaymentGatewaySetting;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSettingSeeder extends Seeder
{
    public function run(): void
    {
        PaymentGatewaySetting::updateOrCreate(['id' => 1], [
            'active_gateway' => 'manual',
            'enabled_gateways' => ['manual', 'xendit', 'midtrans'],
            'is_active' => true,
            'mode' => 'sandbox',
            'currency' => 'IDR',
            'admin_fee' => 4500,
            'payment_timeout_minutes' => 1440,
            'manual_bank_name' => 'BCA',
            'manual_account_number' => '1234567890',
            'manual_account_name' => 'PT Duta Indah Residence',
            'manual_instructions' => 'Transfer sesuai total tagihan ke rekening estate, lalu unggah bukti pembayaran melalui portal penghuni.',
            'proof_max_size_kb' => 5120,
            'proof_allowed_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
            'xendit_public_key' => 'xnd_public_development_demo',
            'midtrans_client_key' => 'SB-Mid-client-development-demo',
            'callback_url' => url('/api/v1/payments/webhooks/manual-demo'),
            'webhook_notes' => 'Seeder hanya memakai data sandbox. Secret key disimpan di environment.',
            'updated_by' => User::where('username', 'superadmin')->value('id'),
        ]);
    }
}
