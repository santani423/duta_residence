<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentSeeder extends Seeder
{
    private const TRANSACTION_PREFIX = 'TRX-IPL-';

    /**
     * Kuitansi, transaksi loket, dan alokasi pembayaran dari buku IPL manual (lihat IplData) -
     * bentuknya sama dengan hasil PaymentService::process() saat kasir menerima pembayaran,
     * dengan nominal pas (tanpa denda/saldo). Tagihan yang dilunasinya dibuat BillingSeeder.
     */
    public function run(): void
    {
        $loket = User::where('username', 'loket')->first() ?: User::where('username', 'root')->first();
        $payments = collect(IplData::payments());
        $units = Unit::with(['cluster', 'resident'])->whereIn('id', $payments->pluck('unit_id')->unique())->get()->keyBy('id');
        $payments = $payments->filter(fn (array $payment) => $units->has($payment['unit_id']))->values();
        $prefixLength = strlen(config('grandduta.receipt_prefix', 'GD'));
        $code = fn (array $payment) => str_replace('.', '', substr($payment['receipt_number'], $prefixLength));
        $now = now();

        $transactions = $payments->map(fn (array $payment) => [
            'transaction_number' => self::TRANSACTION_PREFIX.$code($payment),
            'invoice_number' => 'INV-IPL-'.$code($payment),
            'unit_id' => $payment['unit_id'],
            'subtotal' => $payment['amount'],
            'tax' => 0,
            'admin_fee' => 0,
            'total' => $payment['amount'],
            'currency' => 'IDR',
            'payment_provider' => 'loket',
            'payment_method' => $payment['payment_method_id'],
            'status' => 'paid',
            'paid_at' => $payment['paid_at'],
            'created_by' => $loket?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Bulk upsert (bukan PaymentService) karena jumlahnya ribuan dan seeder dijalankan ke
        // database remote. Timeline penagihan untuk transaksi ini bisa diisi belakangan lewat
        // `php artisan collection:backfill-activities`.
        foreach ($transactions->chunk(500) as $chunk) {
            PaymentTransaction::upsert($chunk->values()->all(), ['transaction_number'], ['unit_id', 'subtotal', 'total', 'payment_method', 'status', 'paid_at', 'updated_at']);
        }

        $transactionIds = PaymentTransaction::query()->where('transaction_number', 'like', self::TRANSACTION_PREFIX.'%')->pluck('id', 'transaction_number');

        $receipts = $payments->map(function (array $payment) use ($units, $transactionIds, $code, $loket, $now) {
            $unit = $units->get($payment['unit_id']);
            $periods = array_keys($payment['shares']);
            $periodList = implode(', ', $periods);

            return [
                'number' => $payment['receipt_number'],
                'payment_transaction_id' => $transactionIds[self::TRANSACTION_PREFIX.$code($payment)] ?? null,
                'unit_id' => $unit->id,
                'transaction_date' => $payment['paid_at'],
                'resident_name' => $payment['payer_name'] ?: ($unit->resident?->name ?? '-'),
                'cluster_name' => $unit->cluster->name,
                'block' => $unit->block,
                'lot_number' => $unit->lot_number,
                'total_billing' => $payment['amount'],
                'total_penalty' => 0,
                'grand_total' => $payment['amount'],
                'deposit_amount' => 0,
                'balance_used' => 0,
                'billing_count' => min(count($periods), 255),
                'billing_periods' => strlen($periodList) <= 200 ? $periodList : reset($periods).' s/d '.end($periods),
                'loket_code' => 'L01',
                'cashier_name' => $payment['cashier_name'],
                'payment_method_id' => $payment['payment_method_id'],
                'payment_channel_id' => $payment['payment_channel_id'],
                'status' => 'paid',
                'notes' => $payment['notes'],
                'created_by' => $loket?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        foreach ($receipts->chunk(500) as $chunk) {
            Receipt::upsert($chunk->values()->all(), ['number'], array_diff(array_keys($chunk->first()), ['number', 'created_at']));
        }

        $this->seedAllocations($payments, $transactionIds, $code, $now);
    }

    /**
     * Pivot billing_payment_transaction + payment_allocations per bulan yang dibayar, seperti
     * yang ditulis PaymentService::process(). Ditulis ulang setiap seeding supaya idempoten.
     */
    private function seedAllocations($payments, $transactionIds, callable $code, $now): void
    {
        $billingIds = Billing::query()
            ->whereIn('unit_id', $payments->pluck('unit_id')->unique())
            ->get(['id', 'unit_id', 'year', 'month'])
            ->mapWithKeys(fn (Billing $billing) => [sprintf('%s|%04d-%02d', $billing->unit_id, $billing->year, $billing->month) => $billing->id]);

        $pivot = [];
        $allocations = [];

        foreach ($payments as $payment) {
            $transactionId = $transactionIds[self::TRANSACTION_PREFIX.$code($payment)] ?? null;
            [$paidYear, $paidMonth] = array_map('intval', explode('-', $payment['paid_at']));

            foreach ($payment['shares'] as $period => $share) {
                $billingId = $billingIds["{$payment['unit_id']}|{$period}"] ?? null;
                if (! $transactionId || ! $billingId) {
                    continue;
                }

                [$year, $month] = array_map('intval', explode('-', $period));
                $pivot[] = ['billing_id' => $billingId, 'payment_transaction_id' => $transactionId];
                $allocations[] = [
                    'billing_id' => $billingId,
                    'payment_transaction_id' => $transactionId,
                    'principal_amount' => $share,
                    'penalty_amount' => 0,
                    'total_amount' => $share,
                    'overdue_months' => max(0, ($paidYear - $year) * 12 + $paidMonth - $month),
                    'penalty_rule_id' => null,
                    'penalty_rule_snapshot' => null,
                    'calculated_at' => $payment['paid_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        PaymentAllocation::query()
            ->whereIn('payment_transaction_id', PaymentTransaction::query()->where('transaction_number', 'like', self::TRANSACTION_PREFIX.'%')->select('id'))
            ->delete();

        foreach (array_chunk($pivot, 500) as $chunk) {
            DB::table('billing_payment_transaction')->insertOrIgnore($chunk);
        }
        foreach (array_chunk($allocations, 500) as $chunk) {
            PaymentAllocation::insert($chunk);
        }
    }
}
