<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use App\Services\ClusterCodeService;

/**
 * Data pelanggan (unit + penghuni) dari ekspor sistem lama: database/data/pelanggan.csv,
 * digabung dengan buku pembayaran IPL docs/final_revisi_1.md (lihat PenghuniData):
 *  - nama penghuni diambil dari pembayaran terakhir di buku IPL bila unitnya tercatat di sana
 *    (data kontak, luas, dan serah terima tetap dari pelanggan.csv);
 *  - unit yang hanya ada di buku IPL (sebagian besar Emerald 48) ditambahkan, cluster-nya dari
 *    kode blok (E1 = Emerald 48 blok 1, E7 = Ruko Emerald blok 7).
 *
 * Format kolom pelanggan.csv (tanpa header):
 *  0 kode unit, 1 nama penghuni, 2 kode cluster, 3 blok, 4 nomor, 5 tipe properti (B/K/P),
 *  6 HP, 7 telepon, 8 alamat KTP, 9 kode kelurahan lama, 10 luas bangunan, 11 luas tanah,
 *  12 email, 13 tanggal serah terima, 14 hunian (1 dihuni / 2 kosong),
 *  15 status (1/2 aktif, 3/0 tidak aktif), 16 kode lain (12 = data uji di sistem lama).
 *
 * Kode kelurahan lama (kolom 9) tidak sama dengan kode wilayah di tabel districts,
 * jadi tidak diimpor.
 */
class PelangganData
{
    public const CSV = 'data/pelanggan.csv';

    /** Nama yang di sistem lama berarti "unit belum ada pemilik". */
    private const EMPTY_NAMES = ['', '0', 'unit available'];

    private static ?array $units = null;

    /** @var array<int, string> */
    private static array $skipped = [];

    /**
     * Unit hasil impor, di-key dengan kode unit. resident bernilai null untuk unit tanpa pemilik.
     *
     * @return array<string, array{id: string, cluster_id: string, block: string, lot_number: string, property_type_id: string, building_area: ?float, land_area: ?float, handover_date: ?string, occupancy_id: ?string, status_id: string, resident: ?array{id: string, name: string, phone: ?string, telephone: ?string, id_card_address: ?string, email: ?string}}>
     */
    public static function units(): array
    {
        if (self::$units !== null) {
            return self::$units;
        }

        $clusterIds = array_column(EstateSeeder::clusters(), 'id');
        $ledger = PenghuniData::units();
        $handle = fopen(database_path(self::CSV), 'r');
        $bySlot = [];
        $csvIds = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $row = array_pad(array_map(fn ($value) => trim((string) $value), $row), 17, '');
            [$id, $name, $clusterId, $block, $lot] = $row;
            $block = strtoupper($block);
            $csvIds[] = $id;

            // Baris uji/contoh di sistem lama: kode bukan 5 digit, kode "00xxx", ditandai 12,
            // atau tidak punya cluster/blok/nomor yang valid.
            if (! preg_match('/^\d{5}$/', $id) || str_starts_with($id, '00') || $row[16] === '12'
                || ! in_array($clusterId, $clusterIds, true) || $block === '' || $lot === '') {
                self::$skipped[] = $id === '' ? "(tanpa kode) {$name}" : "{$id} {$name}";

                continue;
            }

            // Nama penghuni terbaru dari buku IPL menggantikan nama di ekspor lama.
            $name = $ledger[PenghuniData::key($block, $lot)]['name'] ?? $name;
            $hasResident = ! in_array(strtolower($name), self::EMPTY_NAMES, true);
            $statusId = match (true) {
                ! $hasResident => 'RK',
                in_array($row[15], ['0', '3'], true) => 'TA',
                default => 'AK',
            };

            $unit = [
                'id' => $id,
                'cluster_id' => $clusterId,
                'block' => $block,
                'lot_number' => $lot,
                'property_type_id' => PropertyType::normalizeId(in_array($row[5], ['B', 'K', 'P'], true) ? $row[5] : 'B'),
                'building_area' => self::area($row[10]),
                'land_area' => self::area($row[11]),
                'handover_date' => self::date($row[13]),
                'occupancy_id' => $hasResident ? (in_array($row[14], ['1', '2'], true) ? $row[14] : null) : '2',
                'status_id' => $statusId,
                'resident' => $hasResident ? [
                    'id' => 'RS'.str_pad($id, 6, '0', STR_PAD_LEFT),
                    'name' => mb_substr(preg_replace('/\s+/', ' ', $name), 0, 100),
                    'phone' => self::text($row[6], 20),
                    'telephone' => self::text($row[7], 20),
                    'id_card_address' => self::text($row[8], 200),
                    'email' => self::email($row[12]),
                ] : null,
            ];

            // Satu cluster/blok/nomor kadang tercatat dua kali (data lama yang sudah diganti
            // pemilik baru) - simpan yang aktif & punya pemilik, lalu yang kodenya terbaru.
            $slot = "{$clusterId}|{$block}|{$lot}";
            $existing = $bySlot[$slot] ?? null;
            if ($existing && self::rank($existing) > self::rank($unit)) {
                self::$skipped[] = "{$id} {$name} (duplikat {$slot}, dipakai {$existing['id']})";

                continue;
            }
            if ($existing) {
                self::$skipped[] = "{$existing['id']} ".($existing['resident']['name'] ?? '-')." (duplikat {$slot}, dipakai {$id})";
            }

            $bySlot[$slot] = $unit;
        }

        fclose($handle);

        $units = array_column($bySlot, null, 'id');
        $units += self::ledgerOnlyUnits($ledger, $units, $csvIds);
        ksort($units);

        return self::$units = $units;
    }

    /**
     * Unit yang hanya tercatat di buku IPL. Cluster dibaca dari kode blok lewat ClusterCodeService;
     * kode tanpa nomor blok (mis. "JA") memakai cluster blok yang sama di pelanggan.csv. Kode unit
     * baru melanjutkan nomor urut tertinggi cluster tersebut di pelanggan.csv.
     */
    private static function ledgerOnlyUnits(array $ledger, array $units, array $csvIds): array
    {
        $known = [];
        $blockClusters = [];
        $types = [];
        foreach ($units as $unit) {
            $known[PenghuniData::key($unit['block'], $unit['lot_number'])] = true;
            $blockClusters[PenghuniData::block($unit['block'])][$unit['cluster_id']] = true;
            $types[$unit['cluster_id']][$unit['property_type_id']] = ($types[$unit['cluster_id']][$unit['property_type_id']] ?? 0) + 1;
        }

        $codes = app(ClusterCodeService::class);
        $lastSequence = [];
        $unknown = [];
        $added = [];

        foreach (array_diff_key($ledger, $known) as $entry) {
            $parsed = $codes->parse($entry['block']);
            $sameBlock = array_keys($blockClusters[$entry['block']] ?? []);
            $clusterId = $parsed['cluster']->id ?? (count($sameBlock) === 1 ? (string) $sameBlock[0] : null);

            if ($clusterId !== null) {
                $lastSequence[$clusterId] ??= collect($csvIds)->filter(fn ($id) => preg_match('/^\d{5}$/', $id) && str_starts_with($id, $clusterId))
                    ->map(fn ($id) => (int) substr($id, 2))->max() ?? 0;
            }

            if ($clusterId === null || $lastSequence[$clusterId] >= 999) {
                $unknown[] = "{$entry['block']}/{$entry['lot']} {$entry['name']}";

                continue;
            }

            $id = $clusterId.str_pad((string) ++$lastSequence[$clusterId], 3, '0', STR_PAD_LEFT);
            // Tipe properti mengikuti tipe terbanyak di cluster yang sama.
            $clusterTypes = $types[$clusterId] ?? [PropertyType::BANGUNAN => 1];
            arsort($clusterTypes);

            $added[$id] = [
                'id' => $id,
                'cluster_id' => $clusterId,
                'block' => $parsed['code'] ?? $entry['block'],
                'lot_number' => $entry['lot'],
                'property_type_id' => (string) array_key_first($clusterTypes),
                'building_area' => null,
                'land_area' => null,
                'handover_date' => null,
                'occupancy_id' => $entry['occupancy_id'],
                'status_id' => 'AK',
                'resident' => [
                    'id' => 'RS'.str_pad($id, 6, '0', STR_PAD_LEFT),
                    'name' => $entry['name'],
                    'phone' => null,
                    'telephone' => null,
                    'id_card_address' => null,
                    'email' => null,
                ],
            ];
        }

        if ($unknown) {
            self::$skipped[] = PenghuniData::SOURCE.': '.count($unknown).' unit tidak dikenali cluster-nya, tidak diimpor ('.implode(', ', $unknown).')';
        }
        if ($ignored = PenghuniData::ignoredRows()) {
            self::$skipped[] = PenghuniData::SOURCE.": {$ignored} baris dilewati (nama batal/salah atau blok/nomor unit kosong)";
        }

        return $added;
    }

    /**
     * Baris CSV yang tidak diimpor beserta alasannya (untuk ditampilkan seeder).
     *
     * @return array<int, string>
     */
    public static function skipped(): array
    {
        self::units();

        return self::$skipped;
    }

    private static function rank(array $unit): array
    {
        return [$unit['status_id'] === 'AK', $unit['resident'] !== null, $unit['id']];
    }

    private static function text(string $value, int $max): ?string
    {
        $value = preg_replace('/\s+/', ' ', $value);

        return in_array($value, ['', '0', '-'], true) ? null : mb_substr($value, 0, $max);
    }

    private static function area(string $value): ?float
    {
        $value = str_replace(',', '.', $value);

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    private static function date(string $value): ?string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        // 2000-01-01 dipakai sistem lama sebagai pengisi untuk kavling kosong.
        return $value === '2000-01-01' ? null : $value;
    }

    private static function email(string $value): ?string
    {
        $value = strtolower($value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) && strlen($value) <= 100 ? $value : null;
    }
}
