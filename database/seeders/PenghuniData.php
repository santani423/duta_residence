<?php

namespace Database\Seeders;

/**
 * Nama penghuni terbaru per unit dari buku pembayaran IPL: docs/final_revisi_1.md (Sheet1).
 *
 * Kolom yang dipakai (dibaca dari header): Nama Penghuni, block, no unit, TGL Pembayaran, KET (1).
 * Kolom block berisi kode properti = prefix cluster + nomor blok (config/cluster_codes.php),
 * mis. E1 = Emerald 48 blok 1 dan E7 = Ruko Emerald blok 7; cluster-nya ditentukan oleh
 * PelangganData lewat ClusterCodeService.
 *
 * Satu unit bisa tercatat dengan banyak nama (salah ketik, ganti pemilik/penyewa); yang dipakai
 * adalah nama pada pembayaran terakhir (tanggal bayar terbaru, lalu baris paling bawah).
 */
class PenghuniData
{
    public const SOURCE = 'docs/final_revisi_1.md';

    /** Penulisan blok di pelanggan.csv yang berbeda dengan buku IPL. */
    private const BLOCK_ALIASES = ['AL-' => 'AL2'];

    /** Isi kolom nama yang bukan penghuni (kuitansi batal/salah, unit kosong, rumah contoh). */
    private const IGNORED_NAMES = [
        '', '-', '0', '??', 'noname', 'cancel', 'cancle', 'batal', 'salah', 'salah arsip', 'kwitansi salah',
        'kwitansi ga ada', 'system error', 'unit available', 'unit available / grand duta', 'rumah contoh',
    ];

    /** Tiga huruf awal nama bulan (Indonesia/Inggris) => nomor bulan. */
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'peb' => 2, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7,
        'agt' => 8, 'ags' => 8, 'agu' => 8, 'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'nop' => 11,
        'des' => 12, 'dec' => 12,
    ];

    /** KET (1) => hunian (1 dihuni / 2 kosong). */
    private const OCCUPANCY = ['huni' => '1', 'sudah huni' => '1', 'belum huni' => '2', 'kosong' => '2'];

    /** Header tabel di final_revisi_1.md => nama field. */
    private const COLUMNS = [
        'Nama Penghuni' => 'name',
        'block' => 'block',
        'no unit' => 'lot',
        'TGL Pembayaran' => 'paid_at',
        'KET (1)' => 'note',
    ];

    private static ?array $units = null;

    private static int $ignoredRows = 0;

    /**
     * Unit yang tercatat di buku IPL, di-key self::key() dan diurutkan per blok lalu nomor unit.
     *
     * @return array<string, array{block: string, lot: string, name: string, occupancy_id: ?string}>
     */
    public static function units(): array
    {
        if (self::$units !== null) {
            return self::$units;
        }

        $units = [];
        $nameRank = [];
        $occupancyRank = [];

        foreach (self::rows() as $line => $row) {
            $name = preg_replace('/\s+/', ' ', $row['name']);
            // "8&9" atau "6,7,8,9" dicatat ke kavling pertama, sama seperti IplData.
            $lot = ltrim(strtoupper(preg_split('/\s*[&,]\s*/', $row['lot'])[0]), '0');

            if (in_array(strtolower($name), self::IGNORED_NAMES, true) || ! preg_match('/^[A-Z]+-?\d*$/', self::block($row['block']))
                || ! preg_match('/^\d+[A-Z]?$/', $lot)) {
                self::$ignoredRows++;

                continue;
            }

            $key = self::key($row['block'], $lot);
            $rank = [self::date($row['paid_at']), $line];
            $units[$key] ??= ['block' => self::block($row['block']), 'lot' => $lot, 'name' => '', 'occupancy_id' => null];

            if ($rank >= ($nameRank[$key] ?? $rank)) {
                $nameRank[$key] = $rank;
                // Nama yang seluruhnya huruf kecil ("nina nopianti") dirapikan menjadi "Nina Nopianti".
                $units[$key]['name'] = mb_substr(mb_strtolower($name) === $name ? mb_convert_case($name, MB_CASE_TITLE) : $name, 0, 100);
            }

            $occupancy = self::OCCUPANCY[strtolower(trim($row['note']))] ?? null;
            if ($occupancy !== null && $rank >= ($occupancyRank[$key] ?? $rank)) {
                $occupancyRank[$key] = $rank;
                $units[$key]['occupancy_id'] = $occupancy;
            }
        }

        ksort($units, SORT_NATURAL);

        return self::$units = $units;
    }

    /** Jumlah baris yang tidak dipakai (nama batal/salah, blok atau nomor unit kosong/tidak terbaca). */
    public static function ignoredRows(): int
    {
        self::units();

        return self::$ignoredRows;
    }

    /** Kunci pencocokan unit "BLOK|nomor" (nomor tanpa nol di depan), sama untuk buku IPL dan pelanggan.csv. */
    public static function key(string $block, string $lot): string
    {
        return self::block($block).'|'.ltrim(strtoupper(trim($lot)), '0');
    }

    public static function block(string $block): string
    {
        $block = strtoupper(str_replace(' ', '', $block));

        return self::BLOCK_ALIASES[$block] ?? $block;
    }

    /**
     * Tanggal bayar sebagai YYYY-MM-DD untuk mengurutkan; '' bila tidak terbaca atau setelah hari ini.
     * Selain ISO, buku IPL memakai "10 Mei 2012", "29 Des 17", "11 Apr'18", "Maret 2012",
     * "26/01/2016", dan "24-08-22" (hari-bulan-tahun).
     */
    private static function date(string $value): string
    {
        $value = strtolower(trim($value));

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            [, $year, $month, $day] = $m;
        } elseif (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{2}|\d{4})$/', $value, $m)) {
            [, $day, $month, $year] = $m;
        } elseif (preg_match('/^(\d{1,2})?\s*-?\s*([a-z]{3})[a-z]*\s*[-\'=]?\s*(\d{2}|\d{4})$/', $value, $m) && isset(self::MONTHS[$m[2]])) {
            [$day, $month, $year] = [$m[1] ?: 1, self::MONTHS[$m[2]], $m[3]];
        } else {
            return '';
        }

        $year = strlen($year) === 2 ? '20'.$year : $year;
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        return checkdate((int) $month, (int) $day, (int) $year) && $date <= date('Y-m-d') ? $date : '';
    }

    /**
     * Baris tabel transaksi, di-key nomor baris file.
     *
     * @return array<int, array<string, string>>
     */
    private static function rows(): array
    {
        $rows = [];
        $fields = null;

        foreach (file(base_path(self::SOURCE), FILE_IGNORE_NEW_LINES) as $index => $text) {
            $text = trim($text);
            if (! str_starts_with($text, '|')) {
                $fields = null;

                continue;
            }

            $cells = array_map('trim', explode('|', trim($text, '|')));

            if ($fields === null) {
                // Baris pertama tabel = header.
                $fields = array_map(fn ($header) => self::COLUMNS[$header] ?? null, $cells);

                continue;
            }
            if (preg_match('/^:?-+:?$/', $cells[0])) {
                continue;
            }

            $row = array_fill_keys(self::COLUMNS, '');
            foreach ($fields as $position => $field) {
                if ($field !== null) {
                    $row[$field] = $cells[$position] ?? '';
                }
            }
            $rows[$index + 1] = $row;
        }

        return $rows;
    }
}
