<?php

/**
 * Sumber konfigurasi resmi kode cluster Duta Residence.
 *
 * Kunci = clusters.id (database/data/clusters.csv), yaitu kode huruf cluster. Kode properti (mis.
 * "E3") adalah gabungan prefix + nomor blok; keduanya disimpan terpisah (clusters.code_prefix dan
 * units.block_number). Cluster dengan prefix sama (D dan E) dibedakan oleh id cluster (ruko-nya
 * RD dan RE) dan rentang blok yang diizinkan.
 *
 * - legacy_id : kode angka di sistem lama, hanya untuk membaca pelanggan.csv dan kode unit impor.
 * - name      : nama cluster yang harus cocok dengan baris di tabel clusters (kalau berbeda, tidak disinkronkan).
 * - prefix    : awalan kode properti.
 * - type      : 'cluster' (hunian) atau 'ruko'.
 * - blocks    : daftar nomor blok yang diizinkan, null = sesuai data blok (tidak dibatasi).
 *
 * @see App\Services\ClusterCodeService
 */
return [
    'clusters' => [
        'D' => ['legacy_id' => '01', 'name' => 'Dolomite', 'prefix' => 'D', 'type' => 'cluster', 'blocks' => [1, 2, 3, 4, 5, 6]],
        'G' => ['legacy_id' => '02', 'name' => 'Garnet', 'prefix' => 'G', 'type' => 'cluster', 'blocks' => null],
        'J' => ['legacy_id' => '03', 'name' => 'Jade', 'prefix' => 'J', 'type' => 'cluster', 'blocks' => null],
        'AR' => ['legacy_id' => '04', 'name' => 'Amber', 'prefix' => 'AR', 'type' => 'cluster', 'blocks' => null],
        'DD' => ['legacy_id' => '05', 'name' => 'Diamond', 'prefix' => 'DD', 'type' => 'cluster', 'blocks' => null],
        'AM' => ['legacy_id' => '06', 'name' => 'Ruko Amber', 'prefix' => 'AM', 'type' => 'ruko', 'blocks' => null],
        'AL' => ['legacy_id' => '07', 'name' => 'Ruko Alexandrite', 'prefix' => 'AL', 'type' => 'ruko', 'blocks' => null],
        'JA' => ['legacy_id' => '08', 'name' => 'Ruko Jade Arcade', 'prefix' => 'JA', 'type' => 'ruko', 'blocks' => null],
        'C' => ['legacy_id' => '09', 'name' => 'Chrysocolla', 'prefix' => 'C', 'type' => 'cluster', 'blocks' => null],
        'E' => ['legacy_id' => '10', 'name' => 'Emerald 48', 'prefix' => 'E', 'type' => 'cluster', 'blocks' => [1, 2, 3, 4, 5, 6]],
        'AX' => ['legacy_id' => '11', 'name' => 'Alexandrite', 'prefix' => 'AX', 'type' => 'cluster', 'blocks' => null],
        'RE' => ['legacy_id' => '12', 'name' => 'Ruko Emerald', 'prefix' => 'E', 'type' => 'ruko', 'blocks' => [7]],
        'B' => ['legacy_id' => '13', 'name' => 'Beryl', 'prefix' => 'B', 'type' => 'cluster', 'blocks' => null],
        'RD' => ['legacy_id' => '14', 'name' => 'Ruko Dolomite', 'prefix' => 'D', 'type' => 'ruko', 'blocks' => [7, 8]],
    ],
];
