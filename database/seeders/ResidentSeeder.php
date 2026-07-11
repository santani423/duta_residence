<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResidentSeeder extends Seeder
{
    public const SPECIAL = [
        'AL001' => ['email' => 'resident.paid@example.com', 'name' => 'Andi Lunas Pratama', 'scenario' => 'Seluruh tagihan lunas dan pembayaran lancar.'],
        'AL002' => ['email' => 'resident.overdue@example.com', 'name' => 'Bima Menunggak Saputra', 'scenario' => 'Memiliki tagihan jatuh tempo dan denda.'],
        'AL003' => ['email' => 'resident.manual.pending@example.com', 'name' => 'Citra Manual Pending', 'scenario' => 'Pembayaran manual menunggu verifikasi.'],
        'AL004' => ['email' => 'resident.complaint@example.com', 'name' => 'Dewi Komplain Aktif', 'scenario' => 'Memiliki komplain aktif.'],
        'AL005' => ['email' => 'resident.nobills@example.com', 'name' => 'Eka Tanpa Tagihan', 'scenario' => 'Penghuni aktif tanpa tagihan.'],
        'AL006' => ['email' => 'resident.manual.rejected@example.com', 'name' => 'Fajar Manual Ditolak', 'scenario' => 'Pembayaran manual ditolak dan bisa upload ulang.'],
        'AL007' => ['email' => 'resident.xendit@example.com', 'name' => 'Gita Xendit Berhasil', 'scenario' => 'Histori pembayaran Xendit berhasil.'],
        'AL008' => ['email' => 'resident.midtrans.failed@example.com', 'name' => 'Hadi Midtrans Gagal', 'scenario' => 'Histori pembayaran Midtrans gagal.'],
        'AL009' => ['email' => 'resident.maintenance@example.com', 'name' => 'Intan Maintenance Aktif', 'scenario' => 'Memiliki maintenance terjadwal dan selesai.'],
        'AL010' => ['email' => 'resident.inactive@example.com', 'name' => 'Joko Nonaktif', 'scenario' => 'Akun penghuni nonaktif.', 'inactive' => true],
        'AL011' => ['email' => 'resident.partial@example.com', 'name' => 'Kirana Bayar Sebagian', 'scenario' => 'Invoice dengan pembayaran sebagian.'],
        'AL012' => ['email' => 'resident.notifications@example.com', 'name' => 'Laras Banyak Notifikasi', 'scenario' => 'Memiliki banyak notifikasi belum dibaca.'],
        'GA012' => ['email' => 'resident@gd.test', 'name' => 'Budi Santoso', 'scenario' => 'Akun penghuni legacy untuk test loket GA012.'],
    ];

    public function run(): void
    {
        $creator = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();

        foreach (self::SPECIAL as $unitId => $profile) {
            Resident::updateOrCreate(['id' => 'RS'.$unitId], [
                'name' => $profile['name'],
                'phone' => '0812'.str_pad((string) (crc32($unitId) % 100000000), 8, '0', STR_PAD_LEFT),
                'telephone' => null,
                'id_card_address' => null,
                'district_id' => null,
                'email' => $profile['email'],
                'created_by' => $creator?->id,
                'updated_by' => $creator?->id,
            ]);
        }
    }
}
