<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    private int $sequence = 1;

    public function run(): void
    {
        $finance = User::where('username', 'finance')->first() ?: User::where('username', 'root')->first();
        $loket = User::where('username', 'loket')->first() ?: $finance;

        Billing::with('unit.cluster')->where('status_id', '02')->orderBy('id')->limit(900)->get()
            ->each(function (Billing $billing) use ($loket) {
                $receipt = $this->receipt($billing, $loket, 'C', 'L');
                $billing->update(['receipt_number' => $receipt->number, 'loket_code' => 'L01']);

                if ($this->sequence % 3 === 0) {
                    $payment = $this->transaction($billing, $this->sequence % 2 === 0 ? 'midtrans' : 'xendit', 'paid', $loket);
                    $payment->billings()->syncWithoutDetaching([$billing->id]);
                }
            });

        $scenarios = [
            'AL003' => ['manual', 'waiting_verification'],
            'AL006' => ['manual', 'rejected'],
            'AL007' => ['xendit', 'paid'],
            'AL008' => ['midtrans', 'failed'],
            'AL009' => ['midtrans', 'paid'],
            'AL011' => ['manual', 'paid'],
            'AL012' => ['xendit', 'expired'],
        ];

        foreach ($scenarios as $unitId => [$provider, $status]) {
            $billing = Billing::where('unit_id', $unitId)->where('status_id', '01')->whereNotNull('approved_at')->oldest()->first()
                ?: Billing::where('unit_id', $unitId)->oldest()->first();
            if (! $billing) {
                continue;
            }

            $payment = $this->transaction($billing, $provider, $status, $finance);
            $payment->billings()->syncWithoutDetaching([$billing->id]);

            if ($status === 'paid') {
                $receipt = $this->receipt($billing, $finance, $provider === 'manual' ? 'D' : 'D', $provider === 'xendit' ? 'X' : ($provider === 'midtrans' ? 'T' : 'M'));
                $billing->update(['status_id' => '02', 'paid_at' => $payment->paid_at, 'receipt_number' => $receipt->number, 'processed_by' => $finance?->id]);
            }
        }

        foreach (['pending', 'expired', 'failed', 'cancelled', 'refunded'] as $status) {
            $unit = Unit::whereNotIn('id', array_keys($scenarios))->where('status_id', 'AK')->inRandomOrder()->first();
            $billing = $unit ? Billing::where('unit_id', $unit->id)->whereNotNull('approved_at')->oldest()->first() : null;
            if ($billing) {
                $payment = $this->transaction($billing, $status === 'failed' ? 'midtrans' : 'xendit', $status, $finance);
                $payment->billings()->syncWithoutDetaching([$billing->id]);
            }
        }
    }

    private function transaction(Billing $billing, string $provider, string $status, ?User $user): PaymentTransaction
    {
        $subtotal = (float) $billing->amount + (float) $billing->penalty - (float) $billing->discount;
        $adminFee = $provider === 'manual' ? 0 : 4500;
        $number = str_pad((string) $this->sequence++, 6, '0', STR_PAD_LEFT);
        $manual = $provider === 'manual';

        return PaymentTransaction::updateOrCreate(
            ['transaction_number' => "TRX-DEMO-{$number}"],
            [
                'invoice_number' => "INV-DEMO-{$number}",
                'unit_id' => $billing->unit_id,
                'subtotal' => $subtotal,
                'tax' => 0,
                'admin_fee' => $adminFee,
                'total' => $subtotal + $adminFee,
                'currency' => 'IDR',
                'payment_provider' => $provider,
                'payment_method' => $manual ? 'bank_transfer' : ($provider === 'xendit' ? 'xendit_invoice' : 'snap'),
                'provider_reference' => "{$provider}-sandbox-{$number}",
                'status' => $status,
                'payment_url' => $manual ? null : "https://sandbox.{$provider}.example.test/pay/{$number}",
                'expired_at' => in_array($status, ['expired', 'failed', 'cancelled'], true) ? now()->subDay() : now()->addDay(),
                'paid_at' => in_array($status, ['paid', 'refunded'], true) ? now()->subDays(rand(1, 20)) : null,
                'manual_proof_path' => $manual ? "dummy/manual-payments/proof-{$number}.jpg" : null,
                'manual_transfer_date' => $manual ? now()->subDays(rand(1, 7))->toDateString() : null,
                'manual_notes' => $manual ? "Pengirim: {$billing->unit->customer->name}\nBank: BCA\nRekening: 1234****{$number}\nNominal: ".($subtotal + $adminFee) : null,
                'verification_notes' => match ($status) {
                    'rejected' => 'Nominal transfer tidak sesuai atau bukti duplikat.',
                    'paid' => $manual ? 'Bukti valid dan disetujui finance.' : null,
                    'refunded' => 'Refund sandbox selesai.',
                    default => null,
                },
                'verified_by' => in_array($status, ['paid', 'rejected'], true) && $manual ? $user?->id : null,
                'verified_at' => in_array($status, ['paid', 'rejected'], true) && $manual ? now()->subHours(rand(2, 24)) : null,
                'provider_payload' => [
                    'sandbox' => true,
                    'external_id' => "EXT-{$number}",
                    'order_id' => "ORDER-{$number}",
                    'idempotency_key' => (string) Str::uuid(),
                    'simulated_status' => $status,
                ],
                'created_by' => $user?->id,
            ]
        );
    }

    private function receipt(Billing $billing, ?User $user, string $method, string $channel): Receipt
    {
        $number = 'GD.'.now()->format('ym').'.'.str_pad((string) $this->sequence++, 6, '0', STR_PAD_LEFT);
        $total = (float) $billing->amount + (float) $billing->penalty - (float) $billing->discount;

        return Receipt::updateOrCreate(['number' => $number], [
            'unit_id' => $billing->unit_id,
            'transaction_date' => $billing->paid_at ?: now()->subDays(rand(1, 15)),
            'customer_name' => $billing->unit->customer->name,
            'cluster_name' => $billing->unit->cluster->name,
            'block' => $billing->unit->block,
            'lot_number' => $billing->unit->lot_number,
            'total_billing' => $billing->amount,
            'total_penalty' => $billing->penalty,
            'grand_total' => $total,
            'billing_count' => 1,
            'billing_periods' => sprintf('%04d-%02d', $billing->year, $billing->month),
            'loket_code' => 'L01',
            'cashier_name' => $user?->name,
            'payment_method_id' => $method,
            'payment_channel_id' => $channel,
            'status' => 'paid',
            'notes' => 'Receipt demo dari database seeder.',
            'created_by' => $user?->id,
        ]);
    }
}
