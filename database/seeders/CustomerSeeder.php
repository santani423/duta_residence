<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public const SPECIAL = [
        'AL001' => ['email' => 'customer.paid@example.com', 'name' => 'Andi Lunas Pratama', 'scenario' => 'Seluruh tagihan lunas dan pembayaran lancar.'],
        'AL002' => ['email' => 'customer.overdue@example.com', 'name' => 'Bima Menunggak Saputra', 'scenario' => 'Memiliki tagihan jatuh tempo dan denda.'],
        'AL003' => ['email' => 'customer.manual.pending@example.com', 'name' => 'Citra Manual Pending', 'scenario' => 'Pembayaran manual menunggu verifikasi.'],
        'AL004' => ['email' => 'customer.complaint@example.com', 'name' => 'Dewi Komplain Aktif', 'scenario' => 'Memiliki komplain aktif.'],
        'AL005' => ['email' => 'customer.nobills@example.com', 'name' => 'Eka Tanpa Tagihan', 'scenario' => 'Customer aktif tanpa tagihan.'],
        'AL006' => ['email' => 'customer.manual.rejected@example.com', 'name' => 'Fajar Manual Ditolak', 'scenario' => 'Pembayaran manual ditolak dan bisa upload ulang.'],
        'AL007' => ['email' => 'customer.xendit@example.com', 'name' => 'Gita Xendit Berhasil', 'scenario' => 'Histori pembayaran Xendit berhasil.'],
        'AL008' => ['email' => 'customer.midtrans.failed@example.com', 'name' => 'Hadi Midtrans Gagal', 'scenario' => 'Histori pembayaran Midtrans gagal.'],
        'AL009' => ['email' => 'customer.maintenance@example.com', 'name' => 'Intan Maintenance Aktif', 'scenario' => 'Memiliki maintenance terjadwal dan selesai.'],
        'AL010' => ['email' => 'customer.inactive@example.com', 'name' => 'Joko Nonaktif', 'scenario' => 'Akun customer nonaktif.', 'inactive' => true],
        'AL011' => ['email' => 'customer.partial@example.com', 'name' => 'Kirana Bayar Sebagian', 'scenario' => 'Invoice dengan pembayaran sebagian.'],
        'AL012' => ['email' => 'customer.notifications@example.com', 'name' => 'Laras Banyak Notifikasi', 'scenario' => 'Memiliki banyak notifikasi belum dibaca.'],
        'GA012' => ['email' => 'customer@gd.test', 'name' => 'Budi Santoso', 'scenario' => 'Akun customer legacy untuk test loket GA012.'],
    ];

    public function run(): void
    {
        $creator = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();

        foreach (self::SPECIAL as $unitId => $profile) {
            Customer::updateOrCreate(['id' => 'CU'.$unitId], [
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
