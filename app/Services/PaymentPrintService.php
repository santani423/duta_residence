<?php

namespace App\Services;

use App\Models\LandingContactSetting;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

/**
 * Menyusun data kuitansi cetak dari Receipt (loket) atau PaymentTransaction (transfer/gateway).
 * Hanya membaca angka yang sudah tersimpan (alokasi pembayaran, tagihan, kuitansi) dan sisa tagihan
 * dari PenaltyService - tidak ada perhitungan pembayaran baru di sini.
 */
class PaymentPrintService
{
    private const RECEIPT_METHODS = ['C' => 'Cash', 'D' => 'Debit/Transfer'];

    private const RECEIPT_CHANNELS = ['L' => 'Loket', 'M' => 'Bank Transfer', 'Q' => 'QRIS'];

    private const PROVIDERS = ['loket' => 'Loket', 'manual' => 'Transfer', 'xendit' => 'Xendit', 'midtrans' => 'Midtrans'];

    public function __construct(private readonly PenaltyService $penaltyService) {}

    public function forReceipt(Receipt $receipt): array
    {
        $receipt->loadMissing(['unit.cluster', 'billings', 'paymentTransaction.allocations.billing']);
        $allocations = $receipt->paymentTransaction?->allocations ?? collect();

        $rows = $allocations->isNotEmpty()
            ? $this->rowsFromAllocations($allocations)
            : $receipt->billings->map(fn ($billing) => $this->row($billing, (float) $billing->amount - (float) $billing->discount, (float) $billing->penalty))->values();

        $method = self::RECEIPT_METHODS[$receipt->payment_method_id] ?? $receipt->payment_method_id;
        if ($receipt->payment_channel_id) {
            $method .= ' ('.(self::RECEIPT_CHANNELS[$receipt->payment_channel_id] ?? $receipt->payment_channel_id).')';
        }

        $balanceUsed = (float) $receipt->balance_used;
        $deposit = (float) $receipt->deposit_amount;
        $grandTotal = (float) $receipt->grand_total;

        return $this->assemble($receipt->unit_id, $rows, [
            'number' => $receipt->number,
            'date' => $receipt->transaction_date,
            'resident_name' => $receipt->resident_name,
            'address' => trim("{$receipt->cluster_name} {$receipt->block}/{$receipt->lot_number}"),
            'method' => $method,
            'cashier' => $receipt->cashier_name,
            'loket' => $receipt->loket_code,
            'status' => $receipt->status === 'cancelled' ? 'Dibatalkan' : 'Lunas',
            'notes' => $receipt->notes,
            'total_principal' => (float) $receipt->total_billing,
            'total_penalty' => (float) $receipt->total_penalty,
            'grand_total' => $grandTotal,
            'balance_used' => $balanceUsed,
            'deposit' => $deposit,
            // Uang yang benar-benar diterima = tagihan dilunasi - dibayar dari saldo + kelebihan bayar masuk saldo.
            'cash_received' => $grandTotal - $balanceUsed + $deposit,
        ]);
    }

    public function forTransaction(PaymentTransaction $transaction): array
    {
        $transaction->loadMissing(['unit.cluster', 'unit.resident', 'billings', 'allocations.billing']);
        $unit = $transaction->unit;
        $rows = $transaction->allocations->isNotEmpty()
            ? $this->rowsFromAllocations($transaction->allocations)
            : $transaction->billings->map(fn ($billing) => $this->row($billing, (float) $billing->amount - (float) $billing->discount, (float) $billing->penalty))->values();

        $grandTotal = $transaction->allocations->isNotEmpty()
            ? (float) $transaction->allocations->sum('total_amount')
            : (float) $transaction->total;

        return $this->assemble($transaction->unit_id, $rows, [
            'number' => $transaction->invoice_number,
            'date' => $transaction->paid_at ?? $transaction->created_at,
            'resident_name' => $unit?->resident?->name ?? '-',
            'address' => trim(($unit?->cluster?->name ?? '').' '.($unit?->block ?? '').'/'.($unit?->lot_number ?? '')),
            'method' => trim((self::RECEIPT_METHODS[$transaction->payment_method] ?? $transaction->payment_method_label ?? '-').' - '.(self::PROVIDERS[$transaction->payment_provider] ?? $transaction->payment_provider), ' -'),
            'cashier' => $transaction->verifier?->name,
            'loket' => null,
            'status' => $transaction->statusLabel(),
            'notes' => $transaction->manual_notes,
            'receipt_number' => Receipt::query()->where('payment_transaction_id', $transaction->id)->value('number'),
            'total_principal' => (float) $transaction->allocations->sum('principal_amount'),
            'total_penalty' => (float) $transaction->allocations->sum('penalty_amount'),
            'grand_total' => $grandTotal,
            'balance_used' => 0.0,
            'deposit' => 0.0,
            'cash_received' => (float) ($transaction->manual_amount ?: $grandTotal),
        ]);
    }

    public function company(): array
    {
        $site = SiteSetting::query()->first();
        $contact = LandingContactSetting::query()->first();
        $logo = public_path('logo-app.png');

        return [
            'name' => $site?->site_name ?: 'Duta Indah Residences',
            'address' => $contact?->address,
            'phone' => $contact?->phone,
            'email' => $contact?->email,
            'logo' => extension_loaded('gd') && is_file($logo) ? $logo : null,
        ];
    }

    private function assemble(string $unitId, Collection $rows, array $data): array
    {
        $outstanding = $this->penaltyService->calculateUnitOutstanding($unitId);

        return $data + [
            'company' => $this->company(),
            'unit_id' => $unitId,
            'rows' => $rows,
            'total_discount' => (float) $rows->sum('discount'),
            'total_waived' => (float) $rows->sum('waived'),
            'remaining' => (float) $outstanding['total_outstanding'],
            'description' => 'Pembayaran iuran/tagihan periode '.($rows->pluck('period')->filter()->implode(', ') ?: '-'),
        ];
    }

    private function rowsFromAllocations(Collection $allocations): Collection
    {
        return $allocations->map(fn (PaymentAllocation $allocation) => $this->row(
            $allocation->billing,
            (float) $allocation->principal_amount,
            (float) $allocation->penalty_amount,
            $allocation->overdue_months,
        ))->values();
    }

    private function row($billing, float $principalPaid, float $penaltyPaid, ?int $overdueMonths = null): array
    {
        return [
            'period' => $billing ? sprintf('%04d-%02d', $billing->year, $billing->month) : '-',
            'type' => $billing?->billing_type,
            'billed' => (float) ($billing?->amount ?? 0),
            'discount' => (float) ($billing?->discount ?? 0),
            'waived' => (float) ($billing?->penalty_waived_amount ?? 0),
            'principal' => $principalPaid,
            'penalty' => $penaltyPaid,
            'overdue_months' => $overdueMonths,
        ];
    }
}
