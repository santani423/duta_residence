<?php

namespace Database\Seeders;

use App\Models\BillingStatus;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
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
    public const CLUSTERS_CSV = 'data/clusters.csv';

    /**
     * Daftar cluster diambil dari database/data/clusters.csv dengan format
     * "kode","nama","tarif bulanan" (tanpa header).
     *
     * @return array<int, array{id: string, name: string, rate: int, profile: string}>
     */
    public static function clusters(): array
    {
        static $clusters;

        if ($clusters !== null) {
            return $clusters;
        }

        $handle = fopen(database_path(self::CLUSTERS_CSV), 'r');
        $clusters = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if ($row === [null] || count($row) < 3) {
                continue;
            }

            [$id, $name, $rate] = array_map('trim', $row);

            $clusters[] = [
                'id' => $id,
                'name' => $name,
                'rate' => (int) $rate,
                'profile' => str_starts_with($name, 'Ruko') ? 'ruko komersial' : 'hunian reguler',
            ];
        }

        fclose($handle);

        return $clusters;
    }

    public function run(): void
    {
        collect([
            ['id' => 'B', 'name' => 'Bangunan', 'description' => 'Unit bangunan siap huni'],
            ['id' => 'K', 'name' => 'Kavling', 'description' => 'Kavling / lahan tanpa bangunan'],
            ['id' => 'R', 'name' => 'Ruko', 'description' => 'Rumah toko'],
        ])->each(fn ($row) => PropertyType::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '1', 'name' => 'Dihuni'], ['id' => '2', 'name' => 'Kosong'], ['id' => '3', 'name' => 'Sewa'], ['id' => '4', 'name' => 'Booked']])
            ->each(fn ($row) => OccupancyStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([
            ['id' => 'AK', 'name' => 'Aktif', 'description' => 'Penghuni aktif'],
            ['id' => 'RK', 'name' => 'Properti Kosong', 'description' => 'Unit kosong atau renovasi'],
            ['id' => 'TA', 'name' => 'Tidak Aktif', 'description' => 'Penghuni tidak aktif'],
        ])->each(fn ($row) => ResidentStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '01', 'name' => 'Belum Bayar'], ['id' => '02', 'name' => 'Lunas']])
            ->each(fn ($row) => BillingStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'C', 'name' => 'Cash'], ['id' => 'D', 'name' => 'Debit / Transfer'], ['id' => 'S', 'name' => 'Saldo']])
            ->each(fn ($row) => PaymentMethod::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'L', 'name' => 'Loket'], ['id' => 'M', 'name' => 'Manual Transfer'], ['id' => 'X', 'name' => 'Xendit'], ['id' => 'T', 'name' => 'Midtrans'], ['id' => 'Q', 'name' => 'QRIS']])
            ->each(fn ($row) => PaymentChannel::updateOrCreate(['id' => $row['id']], $row));

        Regency::updateOrCreate(['id' => '3671'], ['name' => 'Kota Tangerang']);
        foreach (['367101' => 'Ciledug', '367102' => 'Karang Tengah', '367103' => 'Larangan'] as $id => $name) {
            District::updateOrCreate(['id' => $id], ['regency_id' => '3671', 'name' => $name]);
        }

        foreach (self::clusters() as $index => $row) {
            $operatingSince = now()->subYears(8 - ($index % 5));

            Cluster::updateOrCreate(['id' => $row['id']], [
                'name' => $row['name'],
                'monthly_rate' => $row['rate'],
                'is_active' => true,
                'description' => "{$row['profile']}. Alamat: Boulevard Grand Duta blok {$row['id']}. Fasilitas: taman, keamanan 24 jam, CCTV, jogging track. Pengelola: Estate Office {$row['name']}. Beroperasi sejak ".$operatingSince->format('Y-m-d').'.',
            ]);

            // Mirrors what ClusterController::store() does for clusters created via the API -
            // without this, cluster_rate_schedules stays empty and the tariff history table
            // on the cluster detail page has nothing to show.
            ClusterRateSchedule::updateOrCreate(
                ['cluster_id' => $row['id'], 'effective_date' => $operatingSince->toDateString()],
                [
                    'rate' => $row['rate'],
                    'notes' => 'Tarif awal saat cluster dibuat.',
                    'is_active' => true,
                    'activated_at' => $operatingSince,
                ]
            );
        }
    }
}
