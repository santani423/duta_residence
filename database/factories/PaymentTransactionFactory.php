<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        $provider = fake()->randomElement(['xendit', 'midtrans', 'manual']);
        $total = fake()->randomElement([300000, 350000, 400000, 450000, 600000]);

        return [
            'transaction_number' => 'TRX-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'invoice_number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'unit_id' => 'AL001',
            'subtotal' => $total,
            'tax' => 0,
            'admin_fee' => $provider === 'manual' ? 0 : 4500,
            'total' => $provider === 'manual' ? $total : $total + 4500,
            'currency' => 'IDR',
            'payment_provider' => $provider,
            'payment_method' => $provider === 'manual' ? 'bank_transfer' : 'gateway',
            'provider_reference' => $provider.'-sandbox-'.Str::lower(Str::random(16)),
            'status' => 'pending',
            'payment_url' => $provider === 'manual' ? null : 'https://sandbox.example.test/pay/'.Str::lower(Str::random(12)),
            'expired_at' => now()->addDay(),
            'provider_payload' => ['sandbox' => true, 'seeded' => true],
        ];
    }

    public function xendit(): static
    {
        return $this->state(fn () => ['payment_provider' => 'xendit', 'payment_method' => 'xendit_invoice']);
    }

    public function midtrans(): static
    {
        return $this->state(fn () => ['payment_provider' => 'midtrans', 'payment_method' => 'snap']);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'payment_provider' => 'manual',
            'payment_method' => 'bank_transfer',
            'payment_url' => null,
            'admin_fee' => 0,
            'manual_proof_path' => 'dummy/manual-payments/proof-sandbox.jpg',
            'manual_transfer_date' => now()->subDay(),
            'manual_notes' => "Pengirim: Demo Penghuni\nBank: BCA\nRekening: 1234****7890\nNominal sesuai transfer.",
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid', 'paid_at' => now()->subDays(fake()->numberBetween(1, 30))]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'paid_at' => null]);
    }

    public function waitingVerification(): static
    {
        return $this->manual()->state(fn () => ['status' => 'waiting_verification', 'verified_by' => null, 'verified_at' => null]);
    }

    public function rejected(): static
    {
        return $this->manual()->state(fn () => [
            'status' => 'rejected',
            'verification_notes' => 'Bukti transfer tidak jelas atau nominal tidak sesuai.',
            'verified_at' => now()->subHours(8),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['status' => 'expired', 'expired_at' => now()->subDay()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => 'failed', 'provider_payload' => ['sandbox' => true, 'failure_reason' => 'Simulasi gagal']]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => ['status' => 'refunded', 'paid_at' => now()->subDays(12), 'verification_notes' => 'Refund demo selesai.']);
    }
}
