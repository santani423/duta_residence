<?php

namespace Database\Seeders;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\PenaltyWaiver;
use App\Models\Receipt;
use App\Models\Reversal;
use App\Models\SupervisorAssignment;
use App\Models\SupervisorNotification;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditService;
use App\Services\BillingAdjustmentService;
use App\Services\InstallmentPlanService;
use App\Services\PenaltyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for the Supervisor oversight module. Same discipline as CollectorSeeder: no PDF
 * rendering, no real file uploads, no external HTTP (WhatsApp broadcast recipients are seeded
 * with a pre-set delivery_status directly, never via WhatsAppBroadcastService, so no real
 * Fonnte call happens during seeding). Financial approval demo rows are deliberately
 * conservative: Reversal/PenaltyWaiver examples are only ever left `pending` (created exactly
 * like the real submit endpoints do, never `.approve()`d here) so they never mutate real
 * receipt/billing rows in the dev database; only the two brand-new types (installment plan,
 * billing adjustment) - which have no other-table side effects worth worrying about at
 * `pending` status - get a full pending/approved/rejected spread.
 *
 * Sample login credentials (password is 'password' for all):
 *   username           status     oversees
 *   supervisor.rina     active     Cluster Alamanda & Bougenville (Budi & Siti's clusters)
 *   supervisor.hendra   active     Cluster Cendana & Dahlia (Ahmad & Dewi's clusters)
 *   supervisor.wati     inactive   Cluster Edelweiss
 */
class SupervisorSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'root')->first();
        $auditService = app(AuditService::class);

        $rina = $this->makeSupervisor('supervisor.rina', 'Rina Wijaya', 'SPV-0001', '081234600001', SupervisorProfile::STATUS_ACTIVE, $admin);
        $hendra = $this->makeSupervisor('supervisor.hendra', 'Hendra Kusuma', 'SPV-0002', '081234600002', SupervisorProfile::STATUS_ACTIVE, $admin);
        $wati = $this->makeSupervisor('supervisor.wati', 'Wati Purnama', 'SPV-0003', '081234600003', SupervisorProfile::STATUS_INACTIVE, $admin);

        $this->assignCluster($rina, 'AL', $admin);
        $this->assignCluster($rina, 'BO', $admin);
        $this->assignCluster($hendra, 'CE', $admin);
        $this->assignCluster($hendra, 'DA', $admin);
        $this->assignCluster($wati, 'ED', $admin);

        // --- Approval Center: Reversal (pending only, real receipt, never approved here) ---
        $receipt = Receipt::query()->where('status', '!=', 'cancelled')->latest('transaction_date')->first();
        if ($receipt && ! Reversal::query()->where('receipt_number', $receipt->number)->exists()) {
            $reversal = Reversal::query()->create([
                'receipt_number' => $receipt->number,
                'reason' => 'Kesalahan input nominal oleh loket, perlu dibatalkan dan diproses ulang.',
                'status' => 'pending',
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subHours(6),
            ]);
            app(ApprovalService::class)->openFor($reversal, ApprovalRequest::TYPE_REVERSAL, $admin->id, [
                'reason' => $reversal->reason,
                'amount' => (float) ($receipt->grand_total ?? 0),
            ]);
        }

        // --- Approval Center: PenaltyWaiver (pending only, real billing with real penalty) ---
        $penaltyService = app(PenaltyService::class);
        $billingWithPenalty = Billing::query()->outstanding()->where('is_penalty_eligible', true)
            ->orderByDesc('id')->get()->first(fn (Billing $b) => $penaltyService->calculatePenalty($b) > 1000);
        if ($billingWithPenalty && ! PenaltyWaiver::query()->where('billing_id', $billingWithPenalty->id)->where('status', 'pending')->exists()) {
            try {
                $waiver = app(\App\Services\PenaltyWaiverService::class)->submit(
                    $billingWithPenalty,
                    round(min(10000, $penaltyService->calculatePenalty($billingWithPenalty) * 0.5), 2),
                    'Penghuni mengalami kesulitan ekonomi, mengajukan keringanan sebagian denda.',
                    $admin->id
                );
                app(ApprovalService::class)->openFor($waiver, ApprovalRequest::TYPE_PENALTY_WAIVER, $admin->id, [
                    'reason' => $waiver->reason,
                    'amount' => (float) $waiver->waived_penalty_amount,
                    'related_unit_id' => $billingWithPenalty->unit_id,
                ]);
            } catch (\Throwable) {
                // Dev DB state doesn't currently support a valid waiver amount - skip gracefully.
            }
        }

        // --- Approval Center: Installment Plan (pending, approved, rejected) ---
        $installmentUnit = Billing::query()->outstanding()->orderBy('id')->first()?->unit_id ?? 'AL001';
        $this->installmentPlanDemo($installmentUnit, 'pending', $admin);
        $this->installmentPlanDemo('BO001', 'approved', $admin);
        $this->installmentPlanDemo('CE001', 'rejected', $admin);

        // --- Approval Center: Billing Adjustment (pending, rejected - approved skipped to avoid mutating a real billing's discount in seed data) ---
        $adjustmentBilling = Billing::query()->outstanding()->orderByDesc('id')->first();
        if ($adjustmentBilling) {
            $this->billingAdjustmentDemo($adjustmentBilling, 'pending', $admin);
        }

        // --- Notification/Escalation Center: varied categories, priorities, handled statuses ---
        $this->notification('ptp_due', 'high', 'PTP mendekati jatuh tempo', 'Beberapa janji bayar akan jatuh tempo dalam 2 hari.', 'open');
        $this->notification('broken_promise', 'high', 'Broken Promise terdeteksi', 'Janji bayar tidak dipenuhi tanpa penjadwalan ulang.', 'open');
        $this->notification('collector_inactive', 'normal', 'Kolektor berstatus nonaktif', 'Status akun kolektor perlu ditinjau.', 'in_progress');
        $this->notification('emergency_active', 'critical', 'Kondisi darurat aktif', 'Sinyal darurat penghuni belum ditangani.', 'open');
        $escalated = $this->notification('target_not_met', 'normal', 'Target bulanan belum tercapai', 'Pencapaian kolektor di bawah target bulan ini.', 'escalated');
        if ($escalated->wasRecentlyCreated) {
            $escalated->forceFill([
                'escalation_log' => [[
                    'escalated_by' => $admin->id,
                    'escalated_to' => $admin->id,
                    'note' => 'Perlu tinjauan manajemen area.',
                    'escalated_at' => now()->subHours(3)->toDateTimeString(),
                ]],
                'responsible_user_id' => $admin->id,
            ])->save();
        }
        $this->notification('resident_violation', 'low', 'Pelanggaran penghuni tercatat', 'Laporan pelanggaran tata tertib cluster.', 'handled');

        // --- Broadcast (delivery status pre-set, no real WhatsApp call during seeding) ---
        $broadcast = Broadcast::query()->firstOrCreate(
            ['sender_id' => $admin->id, 'type' => Broadcast::TYPE_ANNOUNCEMENT, 'message' => 'Rapat koordinasi kolektor pekan ini dipindahkan ke hari Jumat pukul 09.00.'],
            ['target_criteria' => ['cluster_id' => null], 'recipient_count' => 2, 'success_count' => 2, 'fail_count' => 0]
        );
        if ($broadcast->wasRecentlyCreated) {
            BroadcastRecipient::query()->create(['broadcast_id' => $broadcast->id, 'recipient_type' => 'collector', 'recipient_id' => null, 'name' => 'Budi Santoso', 'phone' => '6281234500001', 'delivery_status' => 'sent', 'sent_at' => now()->subDay()]);
            BroadcastRecipient::query()->create(['broadcast_id' => $broadcast->id, 'recipient_type' => 'collector', 'recipient_id' => null, 'name' => 'Siti Rahmawati', 'phone' => '6281234500002', 'delivery_status' => 'sent', 'sent_at' => now()->subDay()]);
        }

        $failedBroadcast = Broadcast::query()->firstOrCreate(
            ['sender_id' => $admin->id, 'type' => Broadcast::TYPE_RESIDENT, 'message' => 'Pengingat: mohon segera menyelesaikan tunggakan IPL bulan ini.'],
            ['target_criteria' => ['cluster_id' => 'CE'], 'recipient_count' => 1, 'success_count' => 0, 'fail_count' => 1]
        );
        if ($failedBroadcast->wasRecentlyCreated) {
            BroadcastRecipient::query()->create(['broadcast_id' => $failedBroadcast->id, 'recipient_type' => 'resident', 'recipient_id' => null, 'name' => 'Contoh Penghuni', 'phone' => '', 'delivery_status' => 'failed', 'provider_response' => 'invalid_phone_number', 'sent_at' => now()->subHours(2)]);
        }

        $auditService->log('supervisor_seeder_run', 'supervisors', 'CREATE', $rina, [], [], 'success', 'Data demo supervisor berhasil dibuat/diperbarui.');
    }

    private function makeSupervisor(string $username, string $name, string $code, string $whatsapp, string $accountStatus, User $admin): User
    {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => str_replace('.', '', $username).'@grandduta.test',
                'phone' => $whatsapp,
                'password' => Hash::make('password'),
                'is_active' => $accountStatus === SupervisorProfile::STATUS_ACTIVE,
            ]
        );
        $user->syncRoles(['supervisor']);

        SupervisorProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'supervisor_code' => $code,
                'whatsapp_number' => $whatsapp,
                'address' => 'Jl. Contoh Supervisor No. '.random_int(1, 99).', Tangerang',
                'joined_at' => now()->subMonths(random_int(6, 48))->toDateString(),
                'employment_status' => SupervisorProfile::EMPLOYMENT_PERMANENT,
                'account_status' => $accountStatus,
                'admin_notes' => $accountStatus === SupervisorProfile::STATUS_INACTIVE ? 'Nonaktif sementara, menunggu penugasan area baru.' : null,
                'created_by' => $admin->id,
            ]
        );

        return $user->fresh();
    }

    private function assignCluster(User $supervisor, string $clusterId, User $admin): SupervisorAssignment
    {
        $assignment = SupervisorAssignment::query()->updateOrCreate(
            ['supervisor_id' => $supervisor->id, 'cluster_id' => $clusterId],
            ['is_active' => true, 'status' => SupervisorAssignment::STATUS_ACTIVE, 'start_date' => now()->subMonths(2)->toDateString(), 'assigned_by' => $admin->id]
        );

        if ($assignment->wasRecentlyCreated) {
            app(AuditService::class)->log('supervisor_assignment_created', 'supervisor-assignments', 'CREATE', $assignment, [], $assignment->toArray());
        }

        return $assignment;
    }

    private function installmentPlanDemo(string $unitId, string $outcome, User $admin): void
    {
        $service = app(InstallmentPlanService::class);
        $approvalService = app(ApprovalService::class);

        $existing = \App\Models\InstallmentPlan::query()->where('unit_id', $unitId)->where('reason', 'like', 'Demo supervisor%')->first();
        if ($existing) {
            return;
        }

        $plan = $service->submit(\App\Models\Unit::findOrFail($unitId), [
            'total_outstanding' => 600000,
            'number_of_installments' => 3,
            'frequency' => 'monthly',
            'start_date' => now()->addWeek()->toDateString(),
            'reason' => "Demo supervisor - rencana cicilan {$outcome}.",
        ], $admin->id);

        $approval = $approvalService->openFor($plan, ApprovalRequest::TYPE_INSTALLMENT_PLAN, $admin->id, [
            'reason' => $plan->reason,
            'amount' => (float) $plan->total_outstanding,
            'related_unit_id' => $unitId,
        ]);

        if ($outcome === 'approved') {
            $approvalService->approve($approval, $admin->id, 'Disetujui - riwayat pembayaran penghuni baik.');
        } elseif ($outcome === 'rejected') {
            $approvalService->reject($approval, $admin->id, 'Ditolak - penghuni belum melunasi cicilan sebelumnya.');
        }
    }

    private function billingAdjustmentDemo(Billing $billing, string $outcome, User $admin): void
    {
        $service = app(BillingAdjustmentService::class);
        $approvalService = app(ApprovalService::class);

        $existing = \App\Models\BillingAdjustment::query()->where('billing_id', $billing->id)->where('reason', 'like', 'Demo supervisor%')->first();
        if ($existing) {
            return;
        }

        $adjustment = $service->submit($billing, 'discount', 25000, 'Demo supervisor - kompensasi keluhan layanan.', $admin->id);

        $approval = $approvalService->openFor($adjustment, ApprovalRequest::TYPE_BILLING_ADJUSTMENT, $admin->id, [
            'reason' => $adjustment->reason,
            'amount' => (float) $adjustment->new_value,
            'related_unit_id' => $billing->unit_id,
        ]);

        if ($outcome === 'rejected') {
            $approvalService->reject($approval, $admin->id, 'Ditolak - belum ada bukti keluhan resmi.');
        }
    }

    private function notification(string $category, string $priority, string $title, string $description, string $handledStatus): SupervisorNotification
    {
        return SupervisorNotification::query()->firstOrCreate(
            ['category' => $category, 'title' => $title],
            [
                'priority' => $priority,
                'description' => $description,
                'read_status' => $handledStatus === 'open' ? 'unread' : 'read',
                'handled_status' => $handledStatus,
                'handling_deadline' => now()->addDay(),
            ]
        );
    }
}
