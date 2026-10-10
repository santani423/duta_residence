<?php

namespace App\Services;

use App\Models\CollectionAccountState;
use App\Models\CollectionActivity;
use App\Models\CollectorReminder;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Satu pintu untuk menulis timeline penagihan (collection_activities):
 *  - record(): aktivitas manual (kontak call/WA/SMS/email, catatan, dll.) dari collector;
 *  - recordForSubject(): aktivitas turunan dari entitas lain (visit, PTP, pembayaran, pengingat
 *    WA), dipanggil observer & command backfill - idempoten per (subjek, event).
 * Setelah aktivitas tercatat, cache collection account unit tersebut di-refresh.
 */
class CollectionActivityService
{
    public function __construct(
        private readonly CollectionAccountService $accountService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @param  array{event?: string, channel_result?: ?string, summary?: string, details?: ?array, occurred_at?: mixed, next_follow_up_at?: mixed, latitude?: mixed, longitude?: mixed, client_uuid?: ?string, collector_id?: ?int, subject?: ?Model}  $attributes
     */
    public function record(string $unitId, string $type, array $attributes = [], ?User $actor = null, bool $refresh = true): CollectionActivity
    {
        if ($existing = CollectionActivity::findByClientUuid($attributes['client_uuid'] ?? null)) {
            return $existing;
        }

        $unit = Unit::query()->findOrFail($unitId);
        $subject = $attributes['subject'] ?? null;

        $activity = CollectionActivity::query()->create([
            'unit_id' => $unit->id,
            'customer_resident_id' => $unit->billingPayerResidentId(),
            'collector_id' => $attributes['collector_id'] ?? $this->responsibleCollectorId($unit->id, $actor),
            'type' => $type,
            'event' => $attributes['event'] ?? 'created',
            'channel_result' => $attributes['channel_result'] ?? null,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => $attributes['summary'] ?? $this->defaultSummary($type, $attributes['channel_result'] ?? null),
            'details' => $attributes['details'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
            'next_follow_up_at' => $attributes['next_follow_up_at'] ?? null,
            'latitude' => $attributes['latitude'] ?? null,
            'longitude' => $attributes['longitude'] ?? null,
            'client_uuid' => $attributes['client_uuid'] ?? null,
            'created_by' => $actor?->id,
        ]);

        // Aktivitas turunan sudah diaudit lewat entitas sumbernya; hanya aktivitas manual yang diaudit di sini.
        if ($subject === null) {
            $this->auditService->log('collection_activity_recorded', 'collection', 'CREATE', $activity, [], $activity->toArray());
        }

        if ($refresh) {
            $this->refreshAfterCommit($unit->id);
        }

        return $activity;
    }

    /**
     * Catat aktivitas turunan untuk entitas sumber. Mengembalikan null bila entitas tidak
     * relevan untuk timeline (mis. transaksi gateway yang masih pending) atau event tersebut
     * sudah pernah tercatat.
     */
    public function recordForSubject(Model $subject, ?string $event = null, bool $refresh = true): ?CollectionActivity
    {
        $payload = $this->describeSubject($subject, $event);
        if ($payload === null) {
            return null;
        }

        $exists = CollectionActivity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('event', $payload['event'])
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->record($subject->unit_id, $payload['type'], [...$payload, 'subject' => $subject], $payload['actor'], $refresh);
    }

    public function refreshAfterCommit(string $unitId): void
    {
        DB::afterCommit(fn () => $this->accountService->refresh($unitId));
    }

    /** Collector penanggung jawab: pelaku bila ia collector, selain itu collector utama akun. */
    private function responsibleCollectorId(string $unitId, ?User $actor): ?int
    {
        if ($actor?->hasRole('collector')) {
            return $actor->id;
        }

        return CollectionAccountState::query()->whereKey($unitId)->value('collector_id');
    }

    /**
     * Pemetaan entitas sumber → isi aktivitas. Event dipakai sebagai kunci idempotensi:
     * satu event per subjek.
     *
     * @return array<string, mixed>|null
     */
    private function describeSubject(Model $subject, ?string $event): ?array
    {
        return match (true) {
            $subject instanceof CollectorVisit => $this->describeVisit($subject, $event),
            $subject instanceof PaymentPromise => $this->describePromise($subject, $event),
            $subject instanceof PaymentTransaction => $this->describePayment($subject),
            $subject instanceof CollectorReminder => [
                'type' => CollectionActivity::TYPE_WHATSAPP,
                'event' => 'created',
                'summary' => 'Pengingat WhatsApp dikirim',
                'details' => ['phone' => $subject->phone, 'message' => $subject->message, 'billing_id' => $subject->billing_id],
                'occurred_at' => $subject->sent_at ?? $subject->created_at,
                'collector_id' => $subject->sent_by,
                'actor' => $this->user($subject->sent_by),
            ],
            default => null,
        };
    }

    private function describeVisit(CollectorVisit $visit, ?string $event): array
    {
        $result = $visit->result_code ?? $visit->status;
        $event ??= $this->initialVisitEvent($visit);
        $isScheduled = $event === 'scheduled';
        // Kunjungan yang baru dimulai belum punya hasil final (mis. Selesai menunggu tanda tangan).
        $isStarted = $event === CollectorVisit::LIFECYCLE_IN_PROGRESS;

        return [
            'type' => CollectionActivity::TYPE_VISIT,
            'event' => $event,
            'channel_result' => $isScheduled || $isStarted ? null : $result,
            'summary' => match (true) {
                $isScheduled => 'Kunjungan dijadwalkan: '.$visit->purpose,
                $isStarted => 'Kunjungan dimulai: '.$visit->purpose.($visit->isAwaitingSignature() ? ' — menunggu tanda tangan penghuni' : ''),
                // Hasil "Selesai" hanya bisa final (lifecycle completed) setelah penghuni tanda tangan.
                $event === CollectorVisit::LIFECYCLE_COMPLETED && $visit->status === CollectorVisit::STATUS_COMPLETED => 'Kunjungan selesai dan ditandatangani penghuni: '.$visit->purpose,
                default => 'Kunjungan: '.$visit->purpose.' ('.$result.')',
            },
            'details' => [
                'purpose' => $visit->purpose,
                'status' => $visit->status,
                'lifecycle' => $visit->lifecycle,
                'result_code' => $visit->result_code,
                'result' => $visit->result,
                'met_with' => $visit->met_with,
                'scheduled_date' => $visit->scheduled_date?->toDateString(),
            ],
            'occurred_at' => match (true) {
                $isScheduled => $visit->created_at ?? now(),
                $isStarted => $visit->started_at ?? $visit->visit_date,
                default => $visit->finished_at ?? $visit->visit_date,
            },
            'next_follow_up_at' => $visit->next_visit_date,
            'latitude' => $visit->checkin_latitude ?? $visit->start_latitude,
            'longitude' => $visit->checkin_longitude ?? $visit->start_longitude,
            'collector_id' => $visit->collector_id,
            'actor' => $this->user($visit->updated_by ?? $visit->created_by ?? $visit->collector_id),
        ];
    }

    /**
     * Event pertama visit: dijadwalkan, dimulai (in_progress, mis. menunggu tanda tangan), atau
     * langsung tercatat. Visit yang pernah dimulai (started_at) dan kini final memakai event
     * lifecycle akhirnya - sama dengan yang dicatat observer - supaya backfill tetap idempoten.
     */
    private function initialVisitEvent(CollectorVisit $visit): string
    {
        return match (true) {
            $visit->lifecycle === CollectorVisit::LIFECYCLE_SCHEDULED => 'scheduled',
            $visit->lifecycle === CollectorVisit::LIFECYCLE_IN_PROGRESS => CollectorVisit::LIFECYCLE_IN_PROGRESS,
            $visit->started_at !== null && in_array($visit->lifecycle, [CollectorVisit::LIFECYCLE_COMPLETED, CollectorVisit::LIFECYCLE_FAILED, CollectorVisit::LIFECYCLE_CANCELLED], true) => $visit->lifecycle,
            default => 'created',
        };
    }

    private function describePromise(PaymentPromise $promise, ?string $event): array
    {
        $event ??= 'created';
        $amount = 'Rp '.number_format((float) $promise->promised_amount, 0, ',', '.');
        $date = $promise->promised_date?->translatedFormat('d M Y');

        $summary = match ($event) {
            'created' => "Janji bayar {$amount} pada {$date}",
            PaymentPromise::STATUS_FULFILLED => "Janji bayar {$amount} terpenuhi",
            PaymentPromise::STATUS_BROKEN => "Janji bayar {$amount} ({$date}) tidak ditepati",
            PaymentPromise::STATUS_CANCELLED => "Janji bayar {$amount} dibatalkan",
            PaymentPromise::STATUS_RESCHEDULED => "Janji bayar {$amount} dijadwalkan ulang",
            default => "Janji bayar {$amount}: {$event}",
        };

        return [
            'type' => CollectionActivity::TYPE_PROMISE,
            'event' => $event,
            'summary' => $summary,
            'details' => [
                'promised_amount' => (float) $promise->promised_amount,
                'promised_date' => $promise->promised_date?->toDateString(),
                'payment_method' => $promise->payment_method,
                'status' => $promise->status,
                'billing_id' => $promise->billing_id,
                'reason' => $event === PaymentPromise::STATUS_CANCELLED ? $promise->cancel_reason : $promise->reason,
            ],
            'occurred_at' => $event === 'created' ? ($promise->created_at ?? now()) : now(),
            'next_follow_up_at' => $event === 'created' ? ($promise->follow_up_date ?? $promise->promised_date) : null,
            'collector_id' => $promise->collector_id,
            'actor' => $this->user($event === 'created' ? $promise->created_by : ($promise->updated_by ?? $promise->created_by)),
        ];
    }

    /** Hanya transaksi yang bermakna bagi penagihan: diajukan/menunggu verifikasi, lunas, ditolak, revisi. */
    private function describePayment(PaymentTransaction $transaction): ?array
    {
        $amount = 'Rp '.number_format((float) $transaction->total, 0, ',', '.');

        [$event, $summary, $occurredAt] = match ($transaction->status) {
            'paid' => ['paid', "Pembayaran diterima {$amount}", $transaction->paid_at ?? now()],
            'waiting_verification' => ['submitted', "Pembayaran {$amount} menunggu verifikasi", $transaction->collected_at ?? $transaction->manual_proof_uploaded_at ?? now()],
            'rejected' => ['rejected', "Pembayaran {$amount} ditolak", $transaction->verified_at ?? now()],
            'revision_requested' => ['revision_requested', "Pembayaran {$amount} perlu direvisi", $transaction->revision_requested_at ?? now()],
            default => [null, null, null],
        };

        if ($event === null) {
            return null;
        }

        $actorId = match ($event) {
            'paid', 'rejected' => $transaction->verified_by ?? $transaction->collected_by ?? $transaction->created_by,
            default => $transaction->collected_by ?? $transaction->created_by,
        };

        return [
            'type' => CollectionActivity::TYPE_PAYMENT,
            'event' => $event,
            'summary' => $summary,
            'details' => [
                'transaction_number' => $transaction->transaction_number,
                'total' => (float) $transaction->total,
                'payment_provider' => $transaction->payment_provider,
                'payment_method' => $transaction->payment_method,
                'status' => $transaction->status,
                'reason' => $transaction->rejection_reason ?? $transaction->revision_notes ?? $transaction->verification_notes,
            ],
            'occurred_at' => $occurredAt,
            'collector_id' => $transaction->collected_by ?? $this->collectorOrNull($transaction->created_by),
            'actor' => $this->user($actorId),
        ];
    }

    private function collectorOrNull(?int $userId): ?int
    {
        $user = $this->user($userId);

        return $user?->hasRole('collector') ? $user->id : null;
    }

    private function user(?int $userId): ?User
    {
        return $userId ? User::query()->find($userId) : null;
    }

    private function defaultSummary(string $type, ?string $result): string
    {
        $label = [
            CollectionActivity::TYPE_CALL => 'Telepon',
            CollectionActivity::TYPE_WHATSAPP => 'WhatsApp',
            CollectionActivity::TYPE_SMS => 'SMS',
            CollectionActivity::TYPE_EMAIL => 'Email',
            CollectionActivity::TYPE_NOTE => 'Catatan',
        ][$type] ?? ucfirst($type);

        return $result ? "{$label}: {$result}" : $label;
    }
}
