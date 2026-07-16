<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\NotificationQueue;

/**
 * Detects when an outstanding invoice crosses into a new overdue/penalty tier (0 -> 1-2 bulan
 * -> 3+ bulan) and sends a one-time notification for that crossing. `penalty_notified_tier`
 * on the billing row remembers the last tier notified so re-running this never spams residents.
 */
class PenaltyNotificationService
{
    public function __construct(private readonly PenaltyService $penaltyService) {}

    public function notifyDueChanges(): int
    {
        $notified = 0;

        Billing::query()->with('unit.resident')->outstanding()->chunkById(200, function ($billings) use (&$notified) {
            foreach ($billings as $billing) {
                $overdueMonths = $this->penaltyService->calculateOverdueMonths($billing);
                $tier = match (true) {
                    $overdueMonths <= 0 => 0,
                    $overdueMonths <= 2 => 1,
                    default => 2,
                };
                $lastNotifiedTier = (int) ($billing->penalty_notified_tier ?? 0);

                if ($tier <= $lastNotifiedTier || $tier === 0) {
                    continue;
                }

                $penaltyAmount = $this->penaltyService->calculatePenalty($billing);
                $period = sprintf('%04d-%02d', $billing->year, $billing->month);
                $message = $tier === 1
                    ? "Tagihan unit {$billing->unit_id} periode {$period} telah memasuki tunggakan, dikenakan denda Rp".number_format($penaltyAmount, 0, ',', '.').'.'
                    : "Tagihan unit {$billing->unit_id} periode {$period} menunggak {$overdueMonths} bulan, denda naik menjadi Rp".number_format($penaltyAmount, 0, ',', '.').'.';

                NotificationQueue::query()->create([
                    'unit_id' => $billing->unit_id,
                    'user_id' => null,
                    'type' => $tier === 1 ? 'billing_overdue_started' : 'billing_penalty_tier_increased',
                    'channel' => 'in_app',
                    'recipient' => $billing->unit?->resident?->phone ?: ($billing->unit?->resident?->email ?: $billing->unit_id),
                    'message' => $message,
                    'read_status' => 'unread',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);

                $billing->forceFill(['penalty_notified_tier' => $tier])->saveQuietly();
                $notified++;
            }
        });

        return $notified;
    }
}
