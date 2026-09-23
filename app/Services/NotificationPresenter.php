<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\ClusterRateSchedule;
use App\Models\CollectorProfile;
use App\Models\EmergencyAlert;
use App\Models\MaintenanceRequest;
use App\Models\NotificationQueue;
use App\Models\PaymentPromise;
use App\Models\PaymentScheme;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\ResidentComplaint;
use App\Models\SupervisorNotification;
use Illuminate\Support\Str;

/**
 * Single place that decides how a stored notification looks to API clients and where it
 * should navigate. Web and mobile both consume the same payload, so routing rules live
 * here once instead of being re-derived (and drifting) per client.
 *
 * `reference` is a client-agnostic descriptor: a stable `resource` key (payment, invoice,
 * ...), the resource id, and whether that record still exists. Each client maps `resource`
 * to its own screen; a missing record never hides the notification itself.
 */
class NotificationPresenter
{
    /** Model class stored in reference_type => client-agnostic resource key. */
    public const RESOURCES = [
        PaymentTransaction::class => 'payment',
        Receipt::class => 'receipt',
        Billing::class => 'invoice',
        EmergencyAlert::class => 'emergency_alert',
        ResidentComplaint::class => 'complaint',
        MaintenanceRequest::class => 'maintenance',
        ClusterRateSchedule::class => 'cluster_rate_schedule',
        PaymentPromise::class => 'payment_promise',
        ApprovalRequest::class => 'approval',
        CollectorProfile::class => 'collector',
        PaymentScheme::class => 'payment_scheme',
    ];

    private const RESOURCE_LABELS = [
        'payment' => 'Pembayaran',
        'receipt' => 'Kuitansi',
        'invoice' => 'Tagihan',
        'emergency_alert' => 'Sinyal Darurat',
        'complaint' => 'Komplain',
        'maintenance' => 'Permintaan Perawatan',
        'cluster_rate_schedule' => 'Jadwal Tarif Cluster',
        'payment_promise' => 'Janji Bayar',
        'approval' => 'Permintaan Persetujuan',
        'collector' => 'Kolektor',
        'payment_scheme' => 'Skema Pembayaran',
    ];

    /** type => [category, label] */
    private const TYPES = [
        'payment_proof_uploaded' => ['payment', 'Bukti Pembayaran Diunggah'],
        'payment_received' => ['payment', 'Pembayaran Online Diterima'],
        'payment_verified' => ['payment', 'Pembayaran Diverifikasi'],
        'payment_rejected' => ['payment', 'Pembayaran Ditolak'],
        'payment_success' => ['payment', 'Pembayaran Berhasil'],
        'payment_partial' => ['payment', 'Pembayaran Sebagian Diterima'],
        'payment_failed' => ['payment', 'Pembayaran Gagal'],
        'manual_payment_approved' => ['payment', 'Pembayaran Manual Disetujui'],
        'manual_payment_rejected' => ['payment', 'Pembayaran Manual Ditolak'],
        'receipt_shared' => ['document', 'Kuitansi Dikirim'],
        'document_new' => ['document', 'Dokumen Baru'],
        'billing_new' => ['billing', 'Tagihan Baru'],
        'billing_due_soon' => ['billing', 'Tagihan Mendekati Jatuh Tempo'],
        'billing_overdue' => ['billing', 'Tagihan Terlambat'],
        'billing_overdue_started' => ['billing', 'Tagihan Menunggak'],
        'billing_penalty_tier_increased' => ['billing', 'Denda Tagihan Naik'],
        'complaint_updated' => ['complaint', 'Komplain Diperbarui'],
        'maintenance_scheduled' => ['maintenance', 'Perawatan Dijadwalkan'],
        'maintenance_completed' => ['maintenance', 'Perawatan Selesai'],
        'emergency_alert' => ['emergency', 'Sinyal Darurat'],
        'security_alert' => ['emergency', 'Informasi Keamanan'],
        'announcement_estate' => ['announcement', 'Pengumuman Estate'],
        'manual_notification' => ['announcement', 'Pesan dari Pengelola'],
        'cluster_rate_scheduled' => ['system', 'Perubahan Tarif Dijadwalkan'],
        'cluster_rate_activated' => ['system', 'Tarif Cluster Berubah'],
        'account_changed' => ['system', 'Perubahan Akun'],
        'internal_task' => ['system', 'Tugas Internal'],
        'payment_scheme_submitted' => ['payment_scheme', 'Pengajuan Skema Pembayaran'],
        'payment_scheme_approved' => ['payment_scheme', 'Skema Pembayaran Disetujui'],
        'payment_scheme_rejected' => ['payment_scheme', 'Skema Pembayaran Ditolak'],
        'payment_scheme_cancelled' => ['payment_scheme', 'Skema Pembayaran Dibatalkan'],
    ];

    /** Payload for a NotificationQueue row. Extends the raw attributes so existing clients keep working. */
    public function queue(NotificationQueue $notification): array
    {
        $notification->loadMissing('sender:id,name');
        [$category, $label] = self::TYPES[$notification->type] ?? ['system', $this->humanize($notification->type)];

        return [
            ...$notification->only([
                'id', 'unit_id', 'user_id', 'type', 'channel', 'message', 'read_status', 'read_at',
                'sent_at', 'created_at', 'updated_at', 'data',
            ]),
            'source' => 'queue',
            'category' => $category,
            'category_label' => $this->categoryLabel($category),
            'title' => $notification->title ?: $label,
            'is_read' => $notification->read_status === 'read',
            'sender' => $notification->sender ? ['id' => $notification->sender->id, 'name' => $notification->sender->name] : null,
            'reference' => $this->reference($notification->reference_type, $notification->reference_id),
        ];
    }

    /** Payload for a SupervisorNotification row. */
    public function supervisor(SupervisorNotification $notification): array
    {
        $notification->loadMissing('relatedCollector:id,name', 'responsibleUser:id,name');

        return [
            ...$notification->toArray(),
            'source' => 'supervisor',
            'type' => $notification->category,
            'category_label' => $this->humanize($notification->category),
            'message' => $notification->description,
            'is_read' => $notification->read_status === 'read',
            'read_at' => null,
            'sender' => null,
            'related_collector' => $notification->relatedCollector ? ['id' => $notification->relatedCollector->id, 'name' => $notification->relatedCollector->name] : null,
            'reference' => $this->reference($notification->reference_type, $notification->reference_id),
        ];
    }

    /**
     * @return array{resource: string, resource_label: string, id: string, available: bool, message: ?string}|null
     */
    public function reference(?string $type, ?string $id): ?array
    {
        if (blank($type) || blank($id)) {
            return null;
        }

        $resource = self::RESOURCES[$type] ?? null;
        if ($resource === null) {
            return null;
        }

        // whereKey()->exists() honours SoftDeletes, so a trashed record counts as gone.
        $available = $type::query()->whereKey($id)->exists();
        $label = self::RESOURCE_LABELS[$resource];

        return [
            'resource' => $resource,
            'resource_label' => $label,
            'id' => (string) $id,
            'available' => $available,
            'message' => $available ? null : "{$label} terkait sudah tidak tersedia atau telah dihapus.",
        ];
    }

    /** Fields to store on a new NotificationQueue row so it can be linked back to its resource. */
    public static function referenceFor(object $model): array
    {
        return ['reference_type' => $model::class, 'reference_id' => (string) $model->getKey()];
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'payment' => 'Pembayaran',
            'billing' => 'Tagihan',
            'document' => 'Dokumen',
            'complaint' => 'Komplain',
            'maintenance' => 'Perawatan',
            'emergency' => 'Darurat',
            'announcement' => 'Pengumuman',
            'payment_scheme' => 'Skema Pembayaran',
            default => 'Sistem',
        };
    }

    private function humanize(?string $value): string
    {
        return Str::of((string) $value)->replace('_', ' ')->title()->toString();
    }
}
