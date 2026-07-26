<?php

namespace App\Services;

use App\Models\InstallmentPlan;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Cicilan tunggakan" - an arrears installment plan. Unlike the flat, pre-existing
 * `Installment` model (a free-text ledger note of cash received), this represents an
 * agreed-upon schedule that must be Supervisor-approved before it's considered active.
 * Approving a plan does not move any money by itself - actual `Installment` rows get
 * created against it later as payments come in, unchanged from today's behavior.
 */
class InstallmentPlanService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function submit(Unit $unit, array $data, int $userId): InstallmentPlan
    {
        $plan = InstallmentPlan::query()->create([
            'unit_id' => $unit->id,
            'billing_id' => $data['billing_id'] ?? null,
            'total_outstanding' => round((float) $data['total_outstanding'], 2),
            'number_of_installments' => $data['number_of_installments'],
            'installment_amount' => round((float) $data['total_outstanding'] / max(1, (int) $data['number_of_installments']), 2),
            'frequency' => $data['frequency'] ?? 'monthly',
            'start_date' => $data['start_date'],
            'status' => InstallmentPlan::STATUS_PENDING,
            'reason' => $data['reason'] ?? null,
            'created_by' => $userId,
        ]);

        $this->auditService->log('installment_plan_submitted', 'installment-plans', 'CREATE', $plan, [], $plan->toArray());

        return $plan;
    }

    public function approve(InstallmentPlan $plan, int $userId, ?string $notes = null): InstallmentPlan
    {
        if ($plan->status !== InstallmentPlan::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['Rencana cicilan sudah diproses.']]);
        }

        return DB::transaction(function () use ($plan, $notes) {
            $plan->forceFill(['status' => InstallmentPlan::STATUS_ACTIVE])->save();

            $this->auditService->log('installment_plan_approved', 'installment-plans', 'APPROVE', $plan, [], $plan->toArray(), 'success', $notes);

            return $plan->refresh();
        });
    }

    public function reject(InstallmentPlan $plan, int $userId, ?string $notes = null): InstallmentPlan
    {
        if ($plan->status !== InstallmentPlan::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['Rencana cicilan sudah diproses.']]);
        }

        $plan->forceFill(['status' => InstallmentPlan::STATUS_CANCELLED])->save();

        $this->auditService->log('installment_plan_rejected', 'installment-plans', 'REJECT', $plan, [], $plan->toArray(), 'success', $notes);

        return $plan->refresh();
    }
}
