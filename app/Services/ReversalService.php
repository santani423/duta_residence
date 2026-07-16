<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Reversal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReversalService
{
    public function approve(Reversal $reversal, int $userId, ?string $notes = null): Reversal
    {
        if ($reversal->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Reversal sudah diproses.']]);
        }

        return DB::transaction(function () use ($reversal, $userId, $notes) {
            $receipt = $reversal->receipt()->with(['billings', 'paymentTransaction.allocations.billing'])->lockForUpdate()->firstOrFail();
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
