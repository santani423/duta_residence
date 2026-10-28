<?php

/**
 * Sumber konfigurasi resmi kode cluster Duta Residence.
 *
 * Kunci = clusters.id (database/data/clusters.csv). Kode properti (mis. "E3") adalah gabungan
 * prefix + nomor blok; keduanya disimpan terpisah (clusters.code_prefix dan units.block_number).
 * Cluster dengan prefix sama (D dan E) dibedakan oleh id cluster dan rentang blok yang diizinkan.
 *
 * - name   : nama cluster yang harus cocok dengan baris di tabel clusters (kalau berbeda, tidak disinkronkan).
 * - prefix : awalan kode properti.
 * - type   : 'cluster' (hunian) atau 'ruko'.
 * - blocks : daftar nomor blok yang diizinkan, null = sesuai data blok (tidak dibatasi).
 *
 * @see App\Services\ClusterCodeService
 */
return [
    'clusters' => [
        '01' => ['name' => 'Dolomite', 'prefix' => 'D', 'type' => 'cluster', 'blocks' => [1, 2, 3, 4, 5, 6]],
        '02' => ['name' => 'Garnet', 'prefix' => 'G', 'type' => 'cluster', 'blocks' => null],
        '03' => ['name' => 'Jade', 'prefix' => 'J', 'type' => 'cluster', 'blocks' => null],
        '04' => ['name' => 'Amber', 'prefix' => 'AR', 'type' => 'cluster', 'blocks' => null],
        '05' => ['name' => 'Diamond', 'prefix' => 'DD', 'type' => 'cluster', 'blocks' => null],
        '06' => ['name' => 'Ruko Amber', 'prefix' => 'AM', 'type' => 'ruko', 'blocks' => null],
        '07' => ['name' => 'Ruko Alexandrite', 'prefix' => 'AL', 'type' => 'ruko', 'blocks' => null],
        '08' => ['name' => 'Ruko Jade Arcade', 'prefix' => 'JA', 'type' => 'ruko', 'blocks' => null],
        '09' => ['name' => 'Chrysocolla', 'prefix' => 'C', 'type' => 'cluster', 'blocks' => null],
        '10' => ['name' => 'Emerald 48', 'prefix' => 'E', 'type' => 'cluster', 'blocks' => [1, 2, 3, 4, 5, 6]],
        '11' => ['name' => 'Alexandrite', 'prefix' => 'AX', 'type' => 'cluster', 'blocks' => null],
        '12' => ['name' => 'Ruko Emerald', 'prefix' => 'E', 'type' => 'ruko', 'blocks' => [7]],
        '13' => ['name' => 'Beryl', 'prefix' => 'B', 'type' => 'cluster', 'blocks' => null],
        '14' => ['name' => 'Ruko Dolomite', 'prefix' => 'D', 'type' => 'ruko', 'blocks' => [7, 8]],
    ],
];
