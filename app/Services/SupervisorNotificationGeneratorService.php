<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\CollectorProfile;
use App\Models\EmergencyAlert;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\SupervisorNotification;

/**
 * Scans the operational tables for the conditions in spec section M and opens a
 * SupervisorNotification for each one not already open. Idempotent: never creates a
 * second open notification for the same (category, reference) pair, so re-running this
 * command (scheduled daily, see routes/console.php) never spams the notification center.
 */
class SupervisorNotificationGeneratorService
{
    public function generate(): int
    {
        $created = 0;

        $created += $this->generatePtpDue();
        $created += $this->generateBrokenPromises();
        $created += $this->generateInactiveCollectors();
        $created += $this->generatePaymentsAwaitingVerification();
        $created += $this->generatePendingApprovals();
        $created += $this->generateHighPriorityComplaints();
        $created += $this->generateActiveEmergencies();

        return $created;
    }

    private function openIfAbsent(string $category, ?string $referenceType, ?string $referenceId, array $attrs): int
    {
        $exists = SupervisorNotification::query()
            ->where('category', $category)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('handled_status', '!=', SupervisorNotification::HANDLED_HANDLED)
            ->exists();

        if ($exists) {
            return 0;
        }

        SupervisorNotification::query()->create([
            'category' => $category,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN,
            ...$attrs,
        ]);

        return 1;
    }

    private function generatePtpDue(): int
    {
        $count = 0;
        $promises = PaymentPromise::query()->where('status', 'pending')
            ->whereDate('promised_date', '<=', now()->addDays(2)->toDateString())
            ->with('unit')->get();

        foreach ($promises as $promise) {
            $count += $this->openIfAbsent('ptp_due', PaymentPromise::class, (string) $promise->id, [
                'priority' => SupervisorNotification::PRIORITY_HIGH,
                'title' => "PTP unit {$promise->unit_id} mendekati jatuh tempo",
                'description' => "Janji bayar sebesar Rp".number_format((float) $promise->promised_amount, 0, ',', '.')." jatuh tempo {$promise->promised_date}.",
                'handling_deadline' => $promise->promised_date,
            ]);
        }

        return $count;
    }

    private function generateBrokenPromises(): int
    {
        $count = 0;
        $broken = PaymentPromise::query()->where('status', 'broken')->with('unit')->get();

        foreach ($broken as $promise) {
            $count += $this->openIfAbsent('broken_promise', PaymentPromise::class, (string) $promise->id, [
                'priority' => SupervisorNotification::PRIORITY_HIGH,
                'title' => "Broken Promise pada unit {$promise->unit_id}",
                'description' => 'Janji bayar tidak dipenuhi hingga jatuh tempo tanpa penjadwalan ulang yang disetujui.',
            ]);
        }

        return $count;
    }

    private function generateInactiveCollectors(): int
    {
        $count = 0;
        $profiles = CollectorProfile::query()->whereIn('account_status', ['inactive', 'suspended'])->with('user')->get();

        foreach ($profiles as $profile) {
            $count += $this->openIfAbsent('collector_inactive', CollectorProfile::class, (string) $profile->id, [
                'priority' => SupervisorNotification::PRIORITY_NORMAL,
                'title' => "Kolektor {$profile->user?->name} berstatus {$profile->account_status}",
                'description' => 'Status akun kolektor perlu ditinjau.',
                'related_collector_id' => $profile->user_id,
            ]);
        }

        return $count;
    }

    private function generatePaymentsAwaitingVerification(): int
    {
        $count = 0;
        $pending = PaymentTransaction::query()->where('status', 'waiting_verification')->get();

        foreach ($pending as $transaction) {
            $count += $this->openIfAbsent('payment_awaiting_verification', PaymentTransaction::class, (string) $transaction->id, [
                'priority' => SupervisorNotification::PRIORITY_NORMAL,
                'title' => "Pembayaran #{$transaction->id} menunggu verifikasi",
                'description' => 'Bukti pembayaran manual menunggu verifikasi.',
            ]);
        }

        return $count;
    }

    private function generatePendingApprovals(): int
    {
        $count = 0;
        $pending = ApprovalRequest::query()->pending()->get();

        foreach ($pending as $approval) {
            $count += $this->openIfAbsent('approval_request', ApprovalRequest::class, (string) $approval->id, [
                'priority' => SupervisorNotification::PRIORITY_NORMAL,
                'title' => "Pengajuan {$approval->request_number} menunggu persetujuan",
                'description' => "Jenis: {$approval->type}.",
                'related_collector_id' => $approval->related_collector_id,
            ]);
        }

        return $count;
    }

    private function generateHighPriorityComplaints(): int
    {
        $count = 0;
        $complaints = ResidentComplaint::query()->whereIn('priority', ['high', 'urgent'])->where('status', 'submitted')->get();

        foreach ($complaints as $complaint) {
            $count += $this->openIfAbsent('complaint_high_priority', ResidentComplaint::class, (string) $complaint->id, [
                'priority' => SupervisorNotification::PRIORITY_HIGH,
                'title' => "Keluhan prioritas tinggi pada unit {$complaint->unit_id}",
                'description' => $complaint->description ?? 'Keluhan penghuni memerlukan perhatian segera.',
                'related_collector_id' => $complaint->collector_id,
            ]);
        }

        return $count;
    }

    private function generateActiveEmergencies(): int
    {
        $count = 0;
        $emergencies = EmergencyAlert::query()->where('status', 'active')->get();

        foreach ($emergencies as $emergency) {
            $count += $this->openIfAbsent('emergency_active', EmergencyAlert::class, (string) $emergency->id, [
                'priority' => SupervisorNotification::PRIORITY_CRITICAL,
                'title' => "Kondisi darurat aktif pada unit {$emergency->unit_id}",
                'description' => $emergency->note ?? 'Kondisi darurat belum ditangani.',
            ]);
        }

        return $count;
    }
}
