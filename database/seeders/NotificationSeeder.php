<?php

namespace Database\Seeders;

use App\Models\NotificationQueue;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'billing_new' => 'Tagihan baru sudah terbit.',
            'billing_due_soon' => 'Tagihan Anda mendekati jatuh tempo.',
            'billing_overdue' => 'Tagihan Anda sudah terlambat.',
            'payment_success' => 'Pembayaran berhasil diterima.',
            'payment_failed' => 'Pembayaran gagal diproses.',
            'manual_payment_approved' => 'Pembayaran manual disetujui.',
            'manual_payment_rejected' => 'Pembayaran manual ditolak.',
            'complaint_updated' => 'Komplain Anda diperbarui.',
            'maintenance_scheduled' => 'Maintenance sudah dijadwalkan.',
            'maintenance_completed' => 'Maintenance selesai.',
            'document_new' => 'Dokumen baru tersedia.',
            'announcement_estate' => 'Pengumuman estate terbaru.',
            'security_alert' => 'Informasi keamanan estate.',
            'account_changed' => 'Perubahan akun berhasil disimpan.',
        ];

        Unit::with('resident')->where('status_id', 'AK')->orderBy('id')->limit(260)->get()->each(function (Unit $unit, int $i) use ($types) {
            $count = $unit->id === 'AL012' ? 18 : rand(2, 7);
            $keys = array_keys($types);

            for ($n = 0; $n < $count; $n++) {
                $type = $keys[($i + $n) % count($keys)];
                NotificationQueue::updateOrCreate(
                    ['unit_id' => $unit->id, 'type' => $type, 'message' => $types[$type]." Ref {$unit->id}-{$n}"],
                    [
                        'user_id' => null,
                        'channel' => 'in_app',
                        'recipient' => $unit->resident?->email ?: $unit->resident?->phone,
                        'read_status' => $unit->id === 'AL012' || $n % 3 !== 0 ? 'unread' : 'read',
                        'status' => $n % 5 === 0 ? 'pending' : 'sent',
                        'attempts' => $n % 2,
                        'sent_at' => now()->subDays($n),
                    ]
                );
            }
        });

        User::whereNull('unit_id')->limit(25)->get()->each(function (User $user, int $i) {
            NotificationQueue::updateOrCreate(
                ['user_id' => $user->id, 'type' => 'internal_task', 'message' => "Tugas internal demo {$i} untuk {$user->name}"],
                [
                    'channel' => 'in_app',
                    'recipient' => $user->email,
                    'read_status' => $i % 2 ? 'read' : 'unread',
                    'status' => 'sent',
                    'sent_at' => now()->subHours($i),
                ]
            );
        });
    }
}
