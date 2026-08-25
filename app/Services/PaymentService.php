<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\NotificationQueue;
use App\Models\PaymentAllocation;
use App\Models\PaymentTransaction;
use App\Models\PenaltySetting;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\UnitDeposit;
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
        private readonly UnitBalanceLedgerService $ledgerService,
    ) {}

    /**
     * Read-only dry run of what process() would do for a given cash amount: how much of it
     * (plus any available unit balance, if requested) would be allocated FIFO across
     * outstanding invoices, how much balance would be drawn down, and how much would land
     * back in the unit's balance as a new overpayment credit. `billingIds = null` (the
     * default) previews against every outstanding+approved invoice on the unit, not just a
     * hand-picked subset.
     */
    public function preview(Unit $unit, float $amount, bool $useBalance = true, ?array $billingIds = null): array
    {
        $amount = round(max(0, $amount), 2);
        $now = now();
        $billings = $this->fifoOrder($this->validatedPayableBillings($unit, $billingIds));
        $breakdown = $billings->map(fn (Billing $billing) => [
            'billing' => $billing,
            'calc' => $this->penaltyService->calculateInvoiceTotal($billing, $now),
        ]);

        $totalDue = round($breakdown->sum(fn (array $row) => $row['calc']['total_outstanding']), 2);
        $availableBalance = $this->ledgerService->currentBalance($unit);
        $settlement = $this->computeSettlement($totalDue, $amount, $availableBalance, $useBalance);
        $plan = $this->planAllocations($breakdown, $settlement['amountToApply'], $this->penaltyService->allocationOrder());

        return [
            'items' => $breakdown->map(fn (array $row) => $row['calc'])->values(),
            'total_outstanding' => $totalDue,
            'payment_amount' => $amount,
            'balance_available' => $availableBalance,
            'balance_used' => $settlement['balanceToUse'],
            'amount_allocated' => round($plan->sum('total_pay'), 2),
            'overpayment' => $settlement['overpayment'],
            'remaining_outstanding' => round(max(0, $totalDue - $settlement['amountToApply']), 2),
            'new_balance' => round($availableBalance - $settlement['balanceToUse'] + $settlement['overpayment'], 2),
            'allocations' => $plan->map(fn (array $row) => [
                'billing_id' => $row['billing']->id,
                'year' => $row['billing']->year,
                'month' => $row['billing']->month,
                'principal_amount' => $row['principal_pay'],
                'penalty_amount' => $row['penalty_pay'],
                'total_amount' => $row['total_pay'],
            ])->values(),
        ];
    }

    /**
     * Process a payment against a unit. `billingIds = null` (the new default) settles
     * against every outstanding+approved invoice on the unit, FIFO (oldest period first);
     * passing explicit ids restricts allocation to just those invoices (used by back-payment
     * flows that target specific periods). `amount` is the cash tendered - if omitted it
     * defaults to the full outstanding total minus any balance used (full-payment,
     * backward-compatible). Available unit balance is drawn down first (up to the total
     * due) unless `use_balance` is false, then cash covers the rest; any cash left over
     * becomes a new overpayment credit on the unit's balance.
     */
    public function process(Unit $unit, ?array $billingIds, array $data, int $userId): Receipt
    {
        return DB::transaction(function () use ($unit, $billingIds, $data, $userId) {
            $lockedUnit = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $now = now();
            $billings = $this->fifoOrder($this->validatedPayableBillings($lockedUnit, $billingIds, true));
            $breakdown = $billings->map(fn (Billing $billing) => [
                'billing' => $billing,
                'calc' => $this->penaltyService->calculateInvoiceTotal($billing, $now),
            ]);

            $totalDue = round($breakdown->sum(fn (array $row) => $row['calc']['total_outstanding']), 2);
            $useBalance = (bool) ($data['use_balance'] ?? true);
            $availableBalance = (float) $lockedUnit->balance;
            $defaultAmount = round(max(0, $totalDue - ($useBalance ? min($availableBalance, $totalDue) : 0)), 2);
            $amount = round((float) ($data['amount'] ?? $defaultAmount), 2);

            if ($amount < 0) {
                throw ValidationException::withMessages(['amount' => ['Nominal pembayaran tidak boleh negatif.']]);
            }

            $settlement = $this->computeSettlement($totalDue, $amount, $availableBalance, $useBalance);

            if ($settlement['amountToApply'] <= 0) {
                throw ValidationException::withMessages(['amount' => ['Nominal pembayaran harus lebih besar dari nol.']]);
            }

            $allocationOrder = $this->penaltyService->allocationOrder();
            $plan = $this->planAllocations($breakdown, $settlement['amountToApply'], $allocationOrder);

            if ($plan->isEmpty()) {
                throw ValidationException::withMessages(['billing_ids' => ['Tidak ada tagihan yang dapat dibayar dari daftar ini.']]);
            }

            $allocations = collect();
            $touchedBillings = collect();

            foreach ($plan as $row) {
                /** @var Billing $billing */
                $billing = $row['billing'];
                $calc = $row['calc'];
                $principalPay = $row['principal_pay'];
                $penaltyPay = $row['penalty_pay'];

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
                    'principal_amount' => $principalPay,
                    'penalty_amount' => $penaltyPay,
                    'total_amount' => $row['total_pay'],
                    'overdue_months' => $calc['overdue_months'],
                    'penalty_rule_id' => $calc['penalty_rule']['id'] ?? null,
                    'penalty_rule_snapshot' => $calc['penalty_rule'],
                ]);
                $touchedBillings->push($billing);
            }

            $totalPrincipalPaid = round($allocations->sum('principal_amount'), 2);
            $totalPenaltyPaid = round($allocations->sum('penalty_amount'), 2);
            $receiptNumber = $this->receiptService->generateReceiptNumber();

            $receipt = Receipt::query()->create([
                'number' => $receiptNumber,
                'unit_id' => $lockedUnit->id,
                'transaction_date' => $now,
                'resident_name' => $lockedUnit->resident->name,
                'cluster_name' => $lockedUnit->cluster->name,
                'block' => $lockedUnit->block,
                'lot_number' => $lockedUnit->lot_number,
                'total_billing' => $totalPrincipalPaid,
                'total_penalty' => $totalPenaltyPaid,
                'grand_total' => round($totalPrincipalPaid + $totalPenaltyPaid, 2),
                'deposit_amount' => $settlement['overpayment'],
                'balance_used' => $settlement['balanceToUse'],
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
                'unit_id' => $lockedUnit->id,
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

            if ($settlement['balanceToUse'] > 0) {
                $this->ledgerService->debit($lockedUnit, UnitDeposit::TYPE_BALANCE_USAGE, $settlement['balanceToUse'], [
                    'payment_transaction_id' => $transaction->id,
                    'receipt_number' => $receiptNumber,
                    'notes' => 'Penggunaan saldo untuk pembayaran '.$receiptNumber,
                    'created_by' => $userId,
                ]);
            }

            if ($settlement['overpayment'] > 0) {
                $this->ledgerService->credit($lockedUnit, UnitDeposit::TYPE_PAYMENT_OVERPAYMENT, $settlement['overpayment'], [
                    'payment_transaction_id' => $transaction->id,
                    'receipt_number' => $receiptNumber,
                    'notes' => 'Kelebihan pembayaran pada kuitansi '.$receiptNumber,
                    'created_by' => $userId,
                ]);
            }

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
                'balance_used' => $settlement['balanceToUse'],
                'overpayment' => $settlement['overpayment'],
                'allocations' => $allocations->map(fn ($row) => [
                    'billing_id' => $row['billing']->id,
                    'principal_amount' => $row['principal_amount'],
                    'penalty_amount' => $row['penalty_amount'],
                ])->values()->all(),
            ]);

            $this->notifyPaymentResult($lockedUnit, $touchedBillings);

            return $receipt->load('billings');
        });
    }

    /**
     * Settle every invoice attached to a gateway/manual-transfer transaction once it is
     * confirmed paid (webhook or manual verification). Locks rows and re-checks each
     * billing's current outstanding state - rather than blindly overwriting - so a billing
     * that was already partially (or fully) settled through another channel in the meantime
     * (e.g. a loket cash payment) is topped up correctly instead of double-credited, and a
     * duplicate webhook retry on an already-paid billing is a no-op. Gateway transactions are
     * always created for exactly the selected invoices' outstanding total, so - unlike the
     * loket flow - there is no cash-tendered/overpayment concept here to route to the unit's
     * balance.
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
     * Given available balance and cash tendered, decide how much balance to draw down and
     * how much cash gets applied vs. left over as a new overpayment credit. Balance is drawn
     * down first (up to the total due), then cash covers what's left.
     */
    private function computeSettlement(float $totalDue, float $amount, float $availableBalance, bool $useBalance): array
    {
        $balanceToUse = $useBalance ? round(min(max(0, $availableBalance), $totalDue), 2) : 0.0;
        $remainingDueAfterBalance = round(max(0, $totalDue - $balanceToUse), 2);
        $cashApplied = round(min($amount, $remainingDueAfterBalance), 2);
        $overpayment = round($amount - $cashApplied, 2);
        $amountToApply = round($balanceToUse + $cashApplied, 2);

        return compact('balanceToUse', 'cashApplied', 'overpayment', 'amountToApply');
    }

    /**
     * Pure (non-mutating) FIFO allocation plan: how much of `amountToApply` would land on
     * each invoice, oldest period first, split between principal/penalty per the configured
     * allocation order. Shared by preview() (read-only) and process() (which then applies
     * the plan to the actual Billing rows).
     */
    private function planAllocations(Collection $breakdown, float $amountToApply, string $allocationOrder): Collection
    {
        $remaining = round($amountToApply, 2);
        $plan = collect();

        foreach ($breakdown as $row) {
            if ($remaining <= 0.0) {
                break;
            }

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

            $plan->push([
                'billing' => $row['billing'],
                'calc' => $calc,
                'principal_pay' => round($principalPay, 2),
                'penalty_pay' => round($penaltyPay, 2),
                'total_pay' => round($principalPay + $penaltyPay, 2),
            ]);

            $remaining = round($remaining - $payFor, 2);
        }

        return $plan;
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

    /**
     * `$billingIds = null` fetches every outstanding+approved billing for the unit (the
     * default "pay by amount" flow); an explicit id list restricts allocation to just those
     * invoices (back-payment flows) and is validated for ownership/outstanding/approval as
     * before.
     */
    private function validatedPayableBillings(Unit $unit, ?array $billingIds, bool $lock = false): Collection
    {
        $query = Billing::query()->with('unit.cluster')->where('unit_id', $unit->id);

        if ($billingIds !== null) {
            $query->whereIn('id', $billingIds);
        } else {
            $query->outstanding()->approved();
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $billings = $query->get();

        if ($billingIds !== null && $billings->count() !== count(array_unique($billingIds))) {
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
