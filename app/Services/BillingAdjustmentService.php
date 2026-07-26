<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\BillingAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Penyesuaian tagihan" - unlike the existing instant, single-step `billings.set-discount`
 * path (DiscountService::applyManualDiscount, still used for automatic/rule-based and
 * unreviewed manual discounts), this is a Supervisor-approval-gated adjustment. Reuses
 * DiscountService for the discount case so there is only one place that actually mutates
 * a billing's discount; penalty/principal corrections are simple, wrapped mutations here.
 */
class BillingAdjustmentService
{
    public function __construct(
        private readonly DiscountService $discountService,
        private readonly AuditService $auditService,
    ) {}

    public function submit(Billing $billing, string $type, float $newValue, string $reason, int $userId): BillingAdjustment
    {
        $originalValue = match ($type) {
            BillingAdjustment::TYPE_DISCOUNT => (float) $billing->discount,
            BillingAdjustment::TYPE_PENALTY => (float) $billing->penalty_waived_amount,
            BillingAdjustment::TYPE_PRINCIPAL_CORRECTION => (float) $billing->amount,
        };

        $adjustment = BillingAdjustment::query()->create([
            'billing_id' => $billing->id,
            'adjustment_type' => $type,
            'original_value' => round($originalValue, 2),
            'new_value' => round($newValue, 2),
            'reason' => $reason,
            'status' => BillingAdjustment::STATUS_PENDING,
            'created_by' => $userId,
        ]);

        $this->auditService->log('billing_adjustment_submitted', 'billing-adjustments', 'CREATE', $adjustment, [], $adjustment->toArray());

        return $adjustment;
    }

    public function approve(BillingAdjustment $adjustment, int $userId, ?string $notes = null): BillingAdjustment
    {
        if ($adjustment->status !== BillingAdjustment::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['Penyesuaian tagihan sudah diproses.']]);
        }

        return DB::transaction(function () use ($adjustment, $userId, $notes) {
            $billing = Billing::query()->lockForUpdate()->findOrFail($adjustment->billing_id);
            $old = $billing->toArray();

            match ($adjustment->adjustment_type) {
                BillingAdjustment::TYPE_DISCOUNT => $this->discountService->applyManualDiscount(
                    $billing, (float) $adjustment->new_value, $adjustment->reason ?? 'Penyesuaian tagihan disetujui supervisor', $userId
                ),
                BillingAdjustment::TYPE_PENALTY => $billing->forceFill(['penalty_waived_amount' => (float) $adjustment->new_value])->save(),
                BillingAdjustment::TYPE_PRINCIPAL_CORRECTION => $billing->forceFill(['amount' => (float) $adjustment->new_value])->save(),
            };

            $adjustment->forceFill(['status' => BillingAdjustment::STATUS_APPROVED])->save();

            $this->auditService->log('billing_adjustment_approved', 'billing-adjustments', 'APPROVE', $billing, $old, $billing->refresh()->toArray(), 'success', $notes);

            return $adjustment->refresh();
        });
    }

    public function reject(BillingAdjustment $adjustment, int $userId, ?string $notes = null): BillingAdjustment
    {
        if ($adjustment->status !== BillingAdjustment::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['Penyesuaian tagihan sudah diproses.']]);
        }

        $adjustment->forceFill(['status' => BillingAdjustment::STATUS_REJECTED])->save();

        $this->auditService->log('billing_adjustment_rejected', 'billing-adjustments', 'REJECT', $adjustment, [], $adjustment->toArray(), 'success', $notes);

        return $adjustment->refresh();
    }
}
