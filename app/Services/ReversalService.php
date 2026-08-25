<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Reversal;
use App\Models\UnitDeposit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReversalService
{
    public function __construct(
        private readonly UnitBalanceLedgerService $ledgerService,
    ) {}

    public function approve(Reversal $reversal, int $userId, ?string $notes = null): Reversal
    {
        if ($reversal->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Reversal sudah diproses.']]);
        }

        return DB::transaction(function () use ($reversal, $userId, $notes) {
            $receipt = $reversal->receipt()->with(['unit', 'billings', 'paymentTransaction.allocations.billing'])->lockForUpdate()->firstOrFail();
            $allocations = $receipt->paymentTransaction?->allocations;

            // Prefer the transaction's own allocation rows - they are the authoritative,
            // per-line record of exactly what THIS receipt/transaction paid on each invoice.
            // The receipt_number FK on billings only ever tracks the *most recent* receipt
            // that touched an invoice, so it can no longer be trusted to enumerate "every
            // billing this receipt paid" once an invoice has been paid across more than one
            // receipt (e.g. partial payment now, remainder settled by a later receipt).
            if ($allocations && $allocations->isNotEmpty()) {
                foreach ($allocations as $allocation) {
                    $billing = Billing::query()->whereKey($allocation->billing_id)->lockForUpdate()->first();
                    if ($billing) {
                        $this->rollbackBilling($billing, $receipt->number, (float) $allocation->principal_amount, (float) $allocation->penalty_amount);
                    }
                }
            } else {
                // Legacy receipts created before payment_transaction_id linkage existed:
                // receipt_number is still reliable there since every such billing was only
                // ever touched by a single receipt.
                foreach ($receipt->billings as $billing) {
                    $this->rollbackBilling($billing, $receipt->number, (float) $billing->principal_paid, (float) $billing->penalty_paid);
                }
            }

            if ($receipt->payment_transaction_id) {
                $receipt->paymentTransaction()->update(['status' => 'reversed']);
                $this->reverseLedgerEntries($receipt, $userId);
            }
            $receipt->forceFill(['status' => 'cancelled'])->save();

            $reversal->forceFill([
                'status' => 'approved',
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            app(AuditService::class)->log('reversal_approved', 'reversals', 'APPROVE', $reversal, [], $reversal->toArray());

            return $reversal->refresh();
        });
    }

    public function reject(Reversal $reversal, int $userId, ?string $notes = null): Reversal
    {
        if ($reversal->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Reversal sudah diproses.']]);
        }

        $reversal->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        app(AuditService::class)->log('reversal_rejected', 'reversals', 'REJECT', $reversal, [], $reversal->toArray());

        return $reversal->refresh();
    }

    /**
     * Post offsetting ledger entries for whatever this receipt's payment transaction did to
     * the unit's balance (an overpayment credit and/or a balance-usage debit) - never edit or
     * delete the original unit_deposits rows, so the transaction history stays intact. A
     * reversed overpayment credit is allowed to push the balance negative if the customer
     * has since spent it elsewhere; that's a real state the reconciliation view is meant to
     * surface, not something to silently clamp.
     */
    private function reverseLedgerEntries($receipt, int $userId): void
    {
        $entries = UnitDeposit::query()
            ->where('payment_transaction_id', $receipt->payment_transaction_id)
            ->whereIn('type', [UnitDeposit::TYPE_PAYMENT_OVERPAYMENT, UnitDeposit::TYPE_BALANCE_USAGE])
            ->get();

        if ($entries->isEmpty()) {
            return;
        }

        $unit = $receipt->unit;

        foreach ($entries as $entry) {
            $meta = [
                'payment_transaction_id' => $receipt->payment_transaction_id,
                'receipt_number' => $receipt->number,
                'reversal_of_id' => $entry->id,
                'notes' => 'Pembalikan atas kuitansi '.$receipt->number,
                'created_by' => $userId,
            ];

            if ($entry->isCredit()) {
                $this->ledgerService->debit($unit, UnitDeposit::TYPE_REVERSAL, (float) $entry->amount, $meta, allowNegative: true);
            } else {
                $this->ledgerService->credit($unit, UnitDeposit::TYPE_REVERSAL, (float) $entry->amount, $meta);
            }
        }
    }

    /**
     * Undo exactly the amount this specific receipt contributed to this billing. Only clears
     * receipt_number when it still points at the receipt being reversed - if a later receipt
     * has since paid the remainder (receipt_number now points there), reversing this older
     * receipt must not sever the still-valid link to that newer one.
     */
    private function rollbackBilling(Billing $billing, string $receiptNumber, float $principalToReverse, float $penaltyToReverse): void
    {
        $principalPaid = max(0.0, round((float) $billing->principal_paid - $principalToReverse, 2));
        $penaltyPaid = max(0.0, round((float) $billing->penalty_paid - $penaltyToReverse, 2));
        $hasRemainingPayment = $principalPaid > 0.01 || $penaltyPaid > 0.01;

        $billing->forceFill([
            'status_id' => $hasRemainingPayment ? Billing::STATUS_PARTIAL : Billing::STATUS_UNPAID,
            'principal_paid' => $principalPaid,
            'penalty_paid' => $penaltyPaid,
            'paid_at' => null,
            'receipt_number' => $billing->receipt_number === $receiptNumber ? null : $billing->receipt_number,
            'processed_by' => null,
        ])->save();
    }
}
