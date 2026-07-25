<?php

namespace Database\Seeders;

use App\Models\BillingStatus;
use App\Models\Cluster;
use App\Models\ResidentStatus;
use App\Models\District;
use App\Models\OccupancyStatus;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\PropertyType;
use App\Models\Regency;
use Illuminate\Database\Seeder;

class EstateSeeder extends Seeder
{
    public const CLUSTERS = [
        ['id' => 'AL', 'name' => 'Cluster Alamanda', 'rate' => 350000, 'profile' => 'reguler, tingkat hunian seimbang'],
        ['id' => 'BO', 'name' => 'Cluster Bougenville', 'rate' => 375000, 'profile' => 'premium dengan fasilitas taman keluarga'],
        ['id' => 'CE', 'name' => 'Cluster Cendana', 'rate' => 325000, 'profile' => 'banyak tenant dan pergantian penghuni'],
        ['id' => 'DA', 'name' => 'Cluster Dahlia', 'rate' => 300000, 'profile' => 'cluster baru dengan unit kosong'],
        ['id' => 'ED', 'name' => 'Cluster Edelweiss', 'rate' => 450000, 'profile' => 'premium, pembayaran lancar'],
        ['id' => 'FL', 'name' => 'Cluster Flamboyan', 'rate' => 400000, 'profile' => 'tingkat komplain tinggi'],
        ['id' => 'GA', 'name' => 'Cluster Gardenia', 'rate' => 300000, 'profile' => 'banyak tunggakan dan test loket'],
        ['id' => 'HA', 'name' => 'Cluster Harmoni', 'rate' => 360000, 'profile' => 'keluarga muda dan fasilitas komunal'],
        ['id' => 'IM', 'name' => 'Cluster Imperial', 'rate' => 550000, 'profile' => 'premium hampir penuh'],
        ['id' => 'JA', 'name' => 'Cluster Jasmine', 'rate' => 325000, 'profile' => 'campuran owner dan tenant'],
        ['id' => 'KE', 'name' => 'Cluster Kenanga', 'rate' => 340000, 'profile' => 'okupansi stabil'],
        ['id' => 'LA', 'name' => 'Cluster Lavender', 'rate' => 390000, 'profile' => 'banyak maintenance ringan'],
        ['id' => 'MA', 'name' => 'Cluster Magnolia', 'rate' => 420000, 'profile' => 'unit besar dan family member'],
        ['id' => 'NI', 'name' => 'Cluster Nirwana', 'rate' => 315000, 'profile' => 'banyak unit renovasi'],
        ['id' => 'OR', 'name' => 'Cluster Orchid', 'rate' => 470000, 'profile' => 'premium, pembayaran terbaik'],
    ];

    public function run(): void
    {
        collect([
            ['id' => 'B', 'name' => 'Bangunan', 'description' => 'Unit bangunan siap huni'],
            ['id' => 'K', 'name' => 'Kavling Developer', 'description' => 'Kavling milik developer'],
            ['id' => 'P', 'name' => 'Kavling Penghuni', 'description' => 'Kavling milik penghuni'],
            ['id' => 'R', 'name' => 'Ruko', 'description' => 'Rumah toko'],
        ])->each(fn ($row) => PropertyType::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '1', 'name' => 'Dihuni'], ['id' => '2', 'name' => 'Kosong'], ['id' => '3', 'name' => 'Sewa']])
            ->each(fn ($row) => OccupancyStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([
            ['id' => 'AK', 'name' => 'Aktif', 'description' => 'Penghuni aktif'],
            ['id' => 'RK', 'name' => 'Rumah Kosong', 'description' => 'Unit kosong atau renovasi'],
            ['id' => 'TA', 'name' => 'Tidak Aktif', 'description' => 'Penghuni tidak aktif'],
        ])->each(fn ($row) => ResidentStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '01', 'name' => 'Belum Bayar'], ['id' => '02', 'name' => 'Lunas']])
            ->each(fn ($row) => BillingStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'C', 'name' => 'Cash'], ['id' => 'D', 'name' => 'Debit / Transfer']])
            ->each(fn ($row) => PaymentMethod::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'L', 'name' => 'Loket'], ['id' => 'M', 'name' => 'Manual Transfer'], ['id' => 'X', 'name' => 'Xendit'], ['id' => 'T', 'name' => 'Midtrans'], ['id' => 'Q', 'name' => 'QRIS']])
            ->each(fn ($row) => PaymentChannel::updateOrCreate(['id' => $row['id']], $row));

        Regency::updateOrCreate(['id' => '3671'], ['name' => 'Kota Tangerang']);
        foreach (['367101' => 'Ciledug', '367102' => 'Karang Tengah', '367103' => 'Larangan'] as $id => $name) {
            District::updateOrCreate(['id' => $id], ['regency_id' => '3671', 'name' => $name]);
        }

        foreach (self::CLUSTERS as $index => $row) {
            Cluster::updateOrCreate(['id' => $row['id']], [
                'name' => $row['name'],
                'monthly_rate' => $row['rate'],
                'is_active' => $index !== 13,
                'description' => "{$row['profile']}. Alamat: Boulevard Grand Duta blok {$row['id']}. Fasilitas: taman, keamanan 24 jam, CCTV, jogging track. Pengelola: Estate Office {$row['name']}. Beroperasi sejak ".now()->subYears(8 - ($index % 5))->format('Y-m-d').'.',
            ]);
        }
    }
}
