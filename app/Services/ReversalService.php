<?php

namespace App\Services;

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
            $receipt = $reversal->receipt()->with('billings')->lockForUpdate()->firstOrFail();

            foreach ($receipt->billings as $billing) {
                $billing->forceFill([
                    'status_id' => '01',
                    'penalty' => 0,
                    'paid_at' => null,
                    'receipt_number' => null,
                    'processed_by' => null,
                ])->save();
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
}
