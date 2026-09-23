<?php

namespace App\Services;

use App\Models\NotificationQueue;
use App\Models\PaymentScheme;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * In-app alerts for the payment-scheme (skema pembayaran) approval workflow. Recipients are
 * always resolved through user -> role -> permission, never a hardcoded user id:
 *
 * - Submission: everyone holding `payment-schemes.approve` is reached, except the submitter.
 *   When the submitter is themselves a (limit-bound) Admin, only the unlimited approvers -
 *   Super Admin/root - are notified, since another Admin has nothing to decide here.
 * - Decision (approve/reject): only the scheme's own submitter is reached, regardless of
 *   whether an Admin or a Super Admin decided it.
 */
class PaymentSchemeNotifier
{
    public function __construct(private readonly DiscountService $discountService) {}

    public function submitted(PaymentScheme $scheme, User $submitter): void
    {
        $unitLabel = $this->unitLabel($scheme);

        foreach ($this->approversFor($submitter) as $approver) {
            $this->notify(
                $scheme,
                $approver->id,
                'payment_scheme_submitted',
                "Pengajuan skema pembayaran baru #{$scheme->id} dari {$submitter->name} untuk {$unitLabel}, menunggu persetujuan.",
                $submitter->id,
            );
        }
    }

    public function approved(PaymentScheme $scheme, User $approver): void
    {
        $this->notifySubmitter(
            $scheme,
            $approver,
            'payment_scheme_approved',
            "Skema pembayaran #{$scheme->id} telah disetujui oleh {$approver->name}.",
        );
    }

    public function rejected(PaymentScheme $scheme, User $rejecter, ?string $notes = null): void
    {
        $reason = $notes ? " Alasan: {$notes}" : '';

        $this->notifySubmitter(
            $scheme,
            $rejecter,
            'payment_scheme_rejected',
            "Skema pembayaran #{$scheme->id} ditolak oleh {$rejecter->name}.{$reason}",
        );
    }

    /** The system, not a person, cancelled a still-Pending scheme (stale billing condition or a payment on one of its bills). */
    public function cancelled(PaymentScheme $scheme, string $reason): void
    {
        $scheme->loadMissing('submitter');
        $submitter = $scheme->submitter;

        if (! $submitter) {
            return;
        }

        $this->notify($scheme, $submitter->id, 'payment_scheme_cancelled', "Skema pembayaran #{$scheme->id} dibatalkan otomatis. {$reason}", null);
    }

    /** Everyone who can decide a payment scheme, minus the person submitting it right now. */
    private function approversFor(User $submitter): Collection
    {
        $submitterIsLimitedAdmin = $this->discountService->maximumPercentFor($submitter) !== null;

        return User::permission('payment-schemes.approve')->get()
            ->reject(fn (User $user) => $user->id === $submitter->id)
            ->when($submitterIsLimitedAdmin, fn (Collection $users) => $users->filter(fn (User $user) => $user->hasAnyRole(['root', 'super_admin'])))
            ->values();
    }

    private function notifySubmitter(PaymentScheme $scheme, User $actor, string $type, string $message): void
    {
        $scheme->loadMissing('submitter');
        $submitter = $scheme->submitter;

        if (! $submitter || $submitter->id === $actor->id) {
            return;
        }

        $this->notify($scheme, $submitter->id, $type, $message, $actor->id);
    }

    private function notify(PaymentScheme $scheme, int $userId, string $type, string $message, ?int $senderId): void
    {
        NotificationQueue::query()->create([
            'unit_id' => $scheme->unit_id,
            'user_id' => $userId,
            'type' => $type,
            'sender_id' => $senderId,
            ...NotificationPresenter::referenceFor($scheme),
            'channel' => 'in_app',
            'recipient' => (string) $userId,
            'message' => $message,
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function unitLabel(PaymentScheme $scheme): string
    {
        $scheme->loadMissing('unit.resident');
        $unit = $scheme->unit;
        $resident = $unit?->resident?->name;

        return "Unit {$scheme->unit_id}".($resident ? " ({$resident})" : '');
    }
}
