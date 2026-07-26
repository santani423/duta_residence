<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The single "Approval Center" gateway over 4 request types. Two (Reversal, PenaltyWaiver)
 * already had their own pending->approved/rejected workflow before this feature - this
 * service never duplicates their money-moving logic, it only (a) opens a linked
 * ApprovalRequest row when one of those is submitted, and (b) on approve/reject, delegates
 * to the underlying type-specific service inside one DB transaction and mirrors the
 * decision onto approval_requests in the same transaction, so the two can never disagree.
 */
class ApprovalService
{
    public function __construct(
        private readonly ReversalService $reversalService,
        private readonly PenaltyWaiverService $penaltyWaiverService,
        private readonly InstallmentPlanService $installmentPlanService,
        private readonly BillingAdjustmentService $billingAdjustmentService,
        private readonly AuditService $auditService,
    ) {}

    public function openFor(Model $requestable, string $type, int $requestedBy, array $attrs = []): ApprovalRequest
    {
        return ApprovalRequest::query()->create([
            'request_number' => $this->generateRequestNumber(),
            'type' => $type,
            'requestable_type' => get_class($requestable),
            'requestable_id' => $requestable->getKey(),
            'requested_by' => $requestedBy,
            'status' => ApprovalRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            ...$attrs,
        ]);
    }

    public function approve(ApprovalRequest $approval, int $userId, ?string $notes = null): ApprovalRequest
    {
        $this->assertPending($approval);

        return DB::transaction(function () use ($approval, $userId, $notes) {
            $requestable = $approval->requestable;

            match ($approval->type) {
                ApprovalRequest::TYPE_REVERSAL => $this->reversalService->approve($requestable, $userId, $notes),
                ApprovalRequest::TYPE_PENALTY_WAIVER => $this->penaltyWaiverService->approve($requestable, $userId, $notes),
                ApprovalRequest::TYPE_INSTALLMENT_PLAN => $this->installmentPlanService->approve($requestable, $userId, $notes),
                ApprovalRequest::TYPE_BILLING_ADJUSTMENT => $this->billingAdjustmentService->approve($requestable, $userId, $notes),
                default => throw ValidationException::withMessages(['type' => ['Jenis pengajuan tidak dikenal.']]),
            };

            $approval->forceFill([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'decided_by' => $userId,
                'decided_at' => now(),
                'supervisor_notes' => $notes,
            ])->save();

            $this->auditService->log('approval_approved', 'approvals', 'APPROVE', $approval, [], $approval->toArray());

            return $approval->refresh();
        });
    }

    public function reject(ApprovalRequest $approval, int $userId, string $notes): ApprovalRequest
    {
        $this->assertPending($approval);

        return DB::transaction(function () use ($approval, $userId, $notes) {
            $requestable = $approval->requestable;

            match ($approval->type) {
                ApprovalRequest::TYPE_REVERSAL => $this->reversalService->reject($requestable, $userId, $notes),
                ApprovalRequest::TYPE_PENALTY_WAIVER => $this->penaltyWaiverService->reject($requestable, $userId, $notes),
                ApprovalRequest::TYPE_INSTALLMENT_PLAN => $this->installmentPlanService->reject($requestable, $userId, $notes),
                ApprovalRequest::TYPE_BILLING_ADJUSTMENT => $this->billingAdjustmentService->reject($requestable, $userId, $notes),
                default => throw ValidationException::withMessages(['type' => ['Jenis pengajuan tidak dikenal.']]),
            };

            $approval->forceFill([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'decided_by' => $userId,
                'decided_at' => now(),
                'supervisor_notes' => $notes,
            ])->save();

            $this->auditService->log('approval_rejected', 'approvals', 'REJECT', $approval, [], $approval->toArray());

            return $approval->refresh();
        });
    }

    private function assertPending(ApprovalRequest $approval): void
    {
        if (! in_array($approval->status, [ApprovalRequest::STATUS_SUBMITTED, ApprovalRequest::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages(['status' => ['Pengajuan sudah diproses.']]);
        }
    }

    private function generateRequestNumber(): string
    {
        $max = ApprovalRequest::query()
            ->where('request_number', 'like', 'APR-%')
            ->get()
            ->map(fn (ApprovalRequest $approval) => (int) substr($approval->request_number, 4))
            ->max() ?? 0;

        return 'APR-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
