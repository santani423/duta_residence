<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\PenaltyWaiver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PenaltyWaiverService
{
    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly AuditService $auditService,
    ) {}

    public function submit(Billing $billing, float $waiveAmount, string $reason, int $userId): PenaltyWaiver
    {
        if ($billing->isCancelled()) {
            throw ValidationException::withMessages(['billing_id' => ['Tagihan yang dibatalkan tidak dapat diberikan keringanan denda.']]);
        }

        $currentPenalty = $this->penaltyService->calculatePenalty($billing);

        if ($waiveAmount <= 0 || $waiveAmount > $currentPenalty + 0.01) {
            throw ValidationException::withMessages(['waived_penalty_amount' => ['Nominal keringanan harus lebih dari 0 dan tidak melebihi denda berjalan saat ini.']]);
        }

        $waiver = PenaltyWaiver::query()->create([
            'billing_id' => $billing->id,
            'original_penalty_amount' => round($currentPenalty, 2),
            'waived_penalty_amount' => round($waiveAmount, 2),
            'final_penalty_amount' => round(max(0.0, $currentPenalty - $waiveAmount), 2),
            'reason' => $reason,
            'status' => 'pending',
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        $this->auditService->log('penalty_waiver_submitted', 'billings', 'WAIVE_SUBMIT', $waiver, [], $waiver->toArray());

        return $waiver;
    }

    public function approve(PenaltyWaiver $waiver, int $userId, ?string $notes = null): PenaltyWaiver
    {
        if ($waiver->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Pengajuan keringanan denda sudah diproses.']]);
        }

        return DB::transaction(function () use ($waiver, $userId, $notes) {
            $billing = Billing::query()->lockForUpdate()->findOrFail($waiver->billing_id);
            $oldBilling = $billing->toArray();

            $billing->forceFill([
                'penalty_waived_amount' => round((float) $billing->penalty_waived_amount + $waiver->waived_penalty_amount, 2),
            ])->save();

            $waiver->forceFill([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $this->auditService->log('penalty_waiver_approved', 'billings', 'WAIVE_APPROVE', $billing, $oldBilling, $billing->refresh()->toArray());

            return $waiver->refresh();
        });
    }

    public function reject(PenaltyWaiver $waiver, int $userId, ?string $notes = null): PenaltyWaiver
    {
        if ($waiver->status !== 'pending') {
            throw ValidationException::withMessages(['status' => ['Pengajuan keringanan denda sudah diproses.']]);
        }

        $waiver->forceFill([
            'status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'review_notes' => $notes,
        ])->save();

        $this->auditService->log('penalty_waiver_rejected', 'billings', 'WAIVE_REJECT', $waiver, [], $waiver->toArray());

        return $waiver->refresh();
    }
}
