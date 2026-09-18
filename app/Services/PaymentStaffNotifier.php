<?php

namespace App\Services;

use App\Models\NotificationQueue;
use App\Models\PaymentTransaction;
use App\Models\User;

/**
 * In-app alerts to counter/finance staff when a resident's payment needs attention.
 *
 * Loket has no payments.verify (separation of duties) but handles payments (payments.create),
 * so it is told about every resident payment even though only payments.verify holders can decide.
 */
class PaymentStaffNotifier
{
    public function proofUploaded(PaymentTransaction $payment, ?int $senderId): void
    {
        $this->notify(
            $payment,
            'payment_proof_uploaded',
            "Bukti pembayaran baru dari {$this->residentName($payment)} (Unit {$payment->unit_id}) menunggu verifikasi.",
            $senderId,
        );
    }

    /** A gateway (Xendit/Midtrans) payment that settled without any staff action. */
    public function gatewayPaid(PaymentTransaction $payment): void
    {
        $alreadyNotified = NotificationQueue::query()
            ->where('type', 'payment_received')
            ->where('reference_type', PaymentTransaction::class)
            ->where('reference_id', (string) $payment->getKey())
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $this->notify(
            $payment,
            'payment_received',
            "Pembayaran online dari {$this->residentName($payment)} (Unit {$payment->unit_id}) telah diterima.",
            null,
        );
    }

    private function notify(PaymentTransaction $payment, string $type, string $message, ?int $senderId): void
    {
        foreach (User::permission(['payments.verify', 'payments.create'])->get() as $staff) {
            NotificationQueue::query()->create([
                'unit_id' => $payment->unit_id,
                'user_id' => $staff->id,
                'type' => $type,
                'sender_id' => $senderId,
                ...NotificationPresenter::referenceFor($payment),
                'channel' => 'in_app',
                'recipient' => $staff->id,
                'message' => $message,
                'read_status' => 'unread',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }

    private function residentName(PaymentTransaction $payment): string
    {
        return $payment->unit?->resident?->name ?? (string) $payment->unit_id;
    }
}
