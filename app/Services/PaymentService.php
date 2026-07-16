<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\NotificationQueue;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\PenaltySetting;
use App\Models\Receipt;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly ReceiptService $receiptService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Preview shows, per selected invoice, exactly what will be charged: outstanding
     * principal, dynamically computed penalty for today, and the combined total.
     * Invoices are always evaluated oldest-period-first (FIFO).
     */
    public function preview(Unit $unit, array $billingIds): array
    {
        $billings = $this->fifoOrder($this->validatedPayableBillings($unit, $billingIds));
        $items = $billings->map(fn (Billing $billing) => $this->penaltyService->calculateInvoiceTotal($billing));

        return [
            'items' => $items->values(),
            'total_billing' => round($items->sum('outstanding_principal'), 2),
            'total_penalty' => round($items->sum('outstanding_penalty'), 2),
            'grand_total' => round($items->sum('total_outstanding'), 2),
        ];
    }

    /**
     * Process a payment against one or more invoices. If `amount` is omitted the full
     * outstanding balance of the selected invoices is settled (backward-compatible full
     * payment). If `amount` is less than the total due, it is applied invoice-by-invoice
     * in FIFO (oldest period first) order, splitting each invoice's share between penalty
     * and principal according to the configured allocation order.
     */
    public function process(Unit $unit, array $billingIds, array $data, int $userId): Receipt
    {
        return DB::transaction(function () use ($unit, $billingIds, $data, $userId) {
            $billings = $this->fifoOrder($this->validatedPayableBillings($unit, $billingIds, true));
            $now = now();
            $breakdown = $billings->map(fn (Billing $billing) => [
                'billing' => $billing,
                'calc' => $this->penaltyService->calculateInvoiceTotal($billing, $now),
            ]);

            $totalDue = round($breakdown->sum(fn (array $row) => $row['calc']['total_outstanding']), 2);
            $amount = round((float) ($data['amount'] ?? $totalDue), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => ['Nominal pembayaran harus lebih besar dari nol.']]);
            }

            if ($amount > $totalDue + 0.01) {
                throw ValidationException::withMessages(['amount' => ['Nominal pembayaran tidak boleh melebihi total tagihan yang dipilih.']]);
            }

            $allocationOrder = $this->penaltyService->allocationOrder();
            $remaining = $amount;
            $allocations = collect();
            $touchedBillings = collect();

            foreach ($breakdown as $row) {
                if ($remaining <= 0.0) {
                    break;
                }

                /** @var Billing $billing */
                $billing = $row['billing'];
                $calc = $row['calc'];
                $dueForInvoice = round($calc['total_outstanding'], 2);

                if ($dueForInvoice <= 0.0) {
                    continue;
                }

                $payFor = min($remaining, $dueForInvoice);
                [$principalPay, $penaltyPay] = $this->splitPayment(
                    $payFor,
                    (float) $calc['outstanding_principal'],
                    (float) $calc['outstanding_penalty'],
                    $allocationOrder
                );

                $newPrincipalPaid = round((float) $billing->principal_paid + $principalPay, 2);
                $newPenaltyPaid = round((float) $billing->penalty_paid + $penaltyPay, 2);
                $stillOutstandingPrincipal = round($calc['principal_amount'] - $newPrincipalPaid, 2);
                $stillOutstandingPenalty = round($calc['penalty_amount'] - $newPenaltyPaid, 2);
                $isSettled = $stillOutstandingPrincipal <= 0.01 && $stillOutstandingPenalty <= 0.01;

                $billing->forceFill([
                    'principal_paid' => $newPrincipalPaid,
                    'penalty' => $calc['penalty_amount'],
                    'penalty_paid' => $newPenaltyPaid,
                    'status_id' => $isSettled ? Billing::STATUS_PAID : Billing::STATUS_PARTIAL,
                    'paid_at' => $isSettled ? $now : $billing->paid_at,
                    'processed_by' => $userId,
                    'loket_code' => $data['loket_code'] ?? $billing->loket_code,
                ])->save();

                $allocations->push([
                    'billing' => $billing,
                    'principal_amount' => round($principalPay, 2),
                    'penalty_amount' => round($penaltyPay, 2),
                    'total_amount' => round($principalPay + $penaltyPay, 2),
                    'overdue_months' => $calc['overdue_months'],
                    'penalty_rule_id' => $calc['penalty_rule']['id'] ?? null,
                    'penalty_rule_snapshot' => $calc['penalty_rule'],
                ]);
                $touchedBillings->push($billing);
                $remaining = round($remaining - $payFor, 2);
            }

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages(['billing_ids' => ['Tidak ada tagihan yang dapat dibayar dari daftar ini.']]);
            }

            $totalPrincipalPaid = round($allocations->sum('principal_amount'), 2);
            $totalPenaltyPaid = round($allocations->sum('penalty_amount'), 2);
            $receiptNumber = $this->receiptService->generateReceiptNumber();

            $receipt = Receipt::query()->create([
                'number' => $receiptNumber,
                'unit_id' => $unit->id,
                'transaction_date' => $now,
                'resident_name' => $unit->resident->name,
                'cluster_name' => $unit->cluster->name,
                'block' => $unit->block,
                'lot_number' => $unit->lot_number,
                'total_billing' => $totalPrincipalPaid,
                'total_penalty' => $totalPenaltyPaid,
                'grand_total' => round($totalPrincipalPaid + $totalPenaltyPaid, 2),
                'billing_count' => $touchedBillings->count(),
                'billing_periods' => $touchedBillings->map(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month))->implode(', '),
                'loket_code' => $data['loket_code'] ?? null,
                'cashier_name' => $data['cashier_name'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? 'C',
                'payment_channel_id' => $data['payment_channel_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            // receipt_number tracks "most recent receipt that touched this invoice" so the
            // SPT/receipt document always lists every invoice included in the transaction,
            // not just the ones that happened to reach full settlement.
            foreach ($touchedBillings as $billing) {
                $billing->forceFill(['receipt_number' => $receiptNumber])->save();
            }

            $transaction = PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.$now->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.$now->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'unit_id' => $unit->id,
                'subtotal' => $totalPrincipalPaid,
                'tax' => 0,
                'admin_fee' => 0,
                'total' => round($totalPrincipalPaid + $totalPenaltyPaid, 2),
                'payment_provider' => 'loket',
                'payment_method' => $data['payment_method_id'] ?? 'C',
                'status' => 'paid',
                'paid_at' => $now,
                'created_by' => $userId,
            ]);
            $transaction->billings()->sync($touchedBillings->pluck('id'));
            $receipt->forceFill(['payment_transaction_id' => $transaction->id])->save();

            foreach ($allocations as $allocation) {
                PaymentAllocation::query()->create([
                    'billing_id' => $allocation['billing']->id,
                    'payment_transaction_id' => $transaction->id,
                    'principal_amount' => $allocation['principal_amount'],
                    'penalty_amount' => $allocation['penalty_amount'],
                    'total_amount' => $allocation['total_amount'],
                    'overdue_months' => $allocation['overdue_months'],
                    'penalty_rule_id' => $allocation['penalty_rule_id'],
                    'penalty_rule_snapshot' => $allocation['penalty_rule_snapshot'],
                    'calculated_at' => $now,
                ]);
            }

            $this->auditService->log('payment_processed', 'payments', 'PROCESS', $receipt, [], [
                ...$receipt->toArray(),
                'allocations' => $allocations->map(fn ($row) => [
                    'billing_id' => $row['billing']->id,
                    'principal_amount' => $row['principal_amount'],
                    'penalty_amount' => $row['penalty_amount'],
                ])->values()->all(),
            ]);

            $this->notifyPaymentResult($unit, $touchedBillings);

            return $receipt->load('billings');
        });
    }

    /**
     * Settle every invoice attached to a gateway/manual-transfer transaction once it is
     * confirmed paid (webhook or manual verification). Locks rows and re-checks each
     * billing's current outstanding state - rather than blindly overwriting - so a billing
     * that was already partially (or fully) settled through another channel in the meantime
     * (e.g. a loket cash payment) is topped up correctly instead of double-credited, and a
     * duplicate webhook retry on an already-paid billing is a no-op.
     */
    public function settleGatewayTransaction(PaymentTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $billingIds = $transaction->billings()->pluck('billings.id');
            $now = now();
            $calcDate = $transaction->created_at ?? $now;
            $paidAt = $transaction->paid_at ?? $now;

            $billings = Billing::query()->with('unit')->whereIn('id', $billingIds)->lockForUpdate()->get();

            foreach ($billings as $billing) {
                if (! $billing->isOutstanding()) {
                    continue;
                }

                $calc = $this->penaltyService->calculateInvoiceTotal($billing, $calcDate);
                $principalPay = round((float) $calc['outstanding_principal'], 2);
                $penaltyPay = round((float) $calc['outstanding_penalty'], 2);

                $billing->forceFill([
                    'principal_paid' => round((float) $billing->principal_paid + $principalPay, 2),
                    'penalty' => $calc['penalty_amount'],
                    'penalty_paid' => round((float) $billing->penalty_paid + $penaltyPay, 2),
                    'status_id' => Billing::STATUS_PAID,
                    'paid_at' => $paidAt,
                ])->save();

                PaymentAllocation::query()->create([
                    'billing_id' => $billing->id,
                    'payment_transaction_id' => $transaction->id,
                    'principal_amount' => $principalPay,
                    'penalty_amount' => $penaltyPay,
                    'total_amount' => round($principalPay + $penaltyPay, 2),
                    'overdue_months' => $calc['overdue_months'],
                    'penalty_rule_id' => $calc['penalty_rule']['id'] ?? null,
                    'penalty_rule_snapshot' => $calc['penalty_rule'],
                    'calculated_at' => $calcDate,
                ]);
            }
        });
    }

    private function notifyPaymentResult(Unit $unit, Collection $touchedBillings): void
    {
        $hasPartial = $touchedBillings->contains(fn (Billing $billing) => $billing->status_id === Billing::STATUS_PARTIAL);
        $periods = $touchedBillings->map(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month))->implode(', ');

        NotificationQueue::query()->create([
            'unit_id' => $unit->id,
            'user_id' => null,
            'type' => $hasPartial ? 'payment_partial' : 'payment_success',
            'channel' => 'in_app',
            'recipient' => $unit->resident->phone ?: ($unit->resident->email ?: $unit->id),
            'message' => $hasPartial
                ? "Pembayaran sebagian untuk tagihan periode {$periods} telah diterima. Masih terdapat sisa tagihan."
                : "Pembayaran untuk tagihan periode {$periods} telah berhasil dan lunas.",
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Split a single payment amount between outstanding penalty and outstanding principal
     * according to the configured allocation order. Never allocates more than what is
     * actually outstanding on either side.
     */
    private function splitPayment(float $payFor, float $outstandingPrincipal, float $outstandingPenalty, string $allocationOrder): array
    {
        if ($allocationOrder === PenaltySetting::ALLOCATION_PRINCIPAL_FIRST) {
            $principalPay = min($payFor, $outstandingPrincipal);
            $penaltyPay = min($payFor - $principalPay, $outstandingPenalty);

            return [$principalPay, $penaltyPay];
        }

        $penaltyPay = min($payFor, $outstandingPenalty);
        $principalPay = min($payFor - $penaltyPay, $outstandingPrincipal);

        return [$principalPay, $penaltyPay];
    }

    private function fifoOrder(Collection $billings): Collection
    {
        return $billings->sortBy([['year', 'asc'], ['month', 'asc']])->values();
    }

    private function validatedPayableBillings(Unit $unit, array $billingIds, bool $lock = false): Collection
    {
        $query = Billing::query()
            ->with('unit.cluster')
            ->whereIn('id', $billingIds)
            ->where('unit_id', $unit->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $billings = $query->get();

        if ($billings->count() !== count(array_unique($billingIds))) {
            throw ValidationException::withMessages(['billing_ids' => ['Tagihan tidak ditemukan atau bukan milik unit ini.']]);
        }

        if ($billings->contains(fn (Billing $billing) => ! $billing->isOutstanding())) {
            throw ValidationException::withMessages(['billing_ids' => ['Semua tagihan harus berstatus belum bayar atau sebagian dibayar.']]);
        }

        if ($billings->contains(fn (Billing $billing) => blank($billing->approved_at))) {
            throw ValidationException::withMessages(['billing_ids' => ['Semua tagihan harus sudah disetujui.']]);
        }

        return $billings;
    }
}
