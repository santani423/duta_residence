<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for how a billing's discount is determined: automatically from
 * the discount rule assigned to a unit when a new invoice is generated, or manually
 * overridden by an authorized admin at any point before the invoice is paid.
 */
class DiscountService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Diskon otomatis untuk tagihan baru, berdasarkan aturan yang dikaitkan ke unit
     * (unit->discount_rule_id) - hanya berlaku selama unit ditandai eligible dan aturannya aktif.
     */
    public function calculateForNewBilling(Unit $unit, float $principalAmount): array
    {
        if (! $unit->is_discount_eligible || ! $unit->discount_rule_id) {
            return ['amount' => 0.0, 'rule' => null];
        }

        $rule = $unit->discountRule;

        if (! $rule || ! $rule->is_active) {
            return ['amount' => 0.0, 'rule' => null];
        }

        return ['amount' => $rule->computeDiscount($principalAmount), 'rule' => $rule];
    }

    /**
     * Override manual oleh admin berwenang - bisa dipakai kapan saja selama tagihan belum
     * lunas, bukan hanya saat tagihan dibuat, supaya diskon ad-hoc tetap bisa diberikan.
     * Dibatasi supaya tidak membuat sisa pokok (principal_paid vs principal_amount) negatif.
     */
    public function applyManualDiscount(Billing $billing, float $amount, string $reason, int $userId): Billing
    {
        if (! $billing->isOutstanding()) {
            throw ValidationException::withMessages(['billing_id' => ['Diskon hanya dapat diubah pada tagihan yang belum lunas.']]);
        }

        $maxDiscount = round((float) $billing->amount - (float) $billing->principal_paid, 2);

        if ($amount < 0 || $amount > $maxDiscount) {
            throw ValidationException::withMessages([
                'discount' => ["Nominal diskon harus antara 0 dan Rp".number_format($maxDiscount, 0, ',', '.').' (pokok tagihan dikurangi yang sudah dibayar).'],
            ]);
        }

        $old = $billing->toArray();

        $billing->forceFill([
            'discount' => round($amount, 2),
            'discount_rule_id' => null,
            'discount_set_by' => $userId,
            'discount_set_at' => now(),
            'discount_reason' => $reason,
        ])->save();

        $this->auditService->log('billing_discount_set_manually', 'billings', 'DISCOUNT_SET', $billing, $old, $billing->refresh()->toArray());

        return $billing;
    }
}
