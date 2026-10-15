<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;

/**
 * Data pembayaran IPL dari buku IPL manual (worksheet "ALL" pada "IPKL Manual 2017 UPTODATE")
 * yang sudah dirapikan di docs/sempelIpl.md. File itu satu-satunya sumber data pembayaran IPL
 * untuk seeder; pembayaran yang tidak tercatat di sana tidak diimpor.
 *
 * Setiap baris tabel transaksi di file itu memakai kolom (urutan bebas, dibaca dari header):
 *  Nama, Blok, No. ("8&9" untuk dua kavling), Nominal (Rp) ("350.000"), Tgl Bayar (YYYY-MM-DD),
 *  Periode (YYYY-MM, satu bulan per baris), No. Kwitansi, Cara Bayar (Tunai/Transfer/VA/Debit),
 *  PIC (Loket/Online), Petugas, dan Catatan (opsional).
 */
class IplData
{
    public const SOURCE = 'docs/sempelIpl.md';

    /** Periode IPL pertama yang dicakup data ini. */
    public const START_PERIOD = '2024-01';

    /**
     * Cluster yang IPL-nya ditagih lewat buku ini. Cluster 01-04 dan 10 (Dolomite, Garnet, Jade,
     * Amber, Emerald 48) tidak punya pembayaran lagi sejak 2017/2018, jadi tidak dibuatkan
     * tunggakan - hanya bulan yang memang tercatat dibayar.
     */
    public const BILLED_CLUSTERS = ['05', '06', '07', '08', '09', '11', '12', '13', '14'];

    /** Penulisan blok di buku IPL yang berbeda dengan pelanggan.csv. */
    private const BLOCK_ALIASES = ['AL2' => 'AL-'];

    /** Header tabel transaksi di sempelIpl.md => nama field. */
    private const COLUMNS = [
        'Nama' => 'name',
        'Blok' => 'block',
        'No.' => 'lot',
        'Nominal (Rp)' => 'amount',
        'Tgl Bayar' => 'paid_at',
        'Periode' => 'period',
        'No. Kwitansi' => 'receipt',
        'Cara Bayar' => 'method',
        'PIC' => 'pic',
        'Petugas' => 'cashier',
        'Catatan' => 'note',
    ];

    private static ?array $payments = null;

    /** @var array<int, string> */
    private static array $skipped = [];

    /**
     * Pembayaran hasil impor, urut tanggal bayar. Setiap baris melunasi satu periode, jadi
     * shares berisi satu bulan senilai nominal yang dibayar.
     *
     * @return array<int, array{line: int, receipt_number: string, unit_id: string, payer_name: string, paid_at: string, amount: int, shares: array<string, int>, payment_method_id: string, payment_channel_id: string, cashier_name: ?string, notes: string}>
     */
    public static function payments(): array
    {
        if (self::$payments !== null) {
            return self::$payments;
        }

        $units = self::unitsByBlockLot();
        $payments = [];

        foreach (self::rows() as $line => $row) {
            $label = "baris {$line} ({$row['name']} {$row['block']}/{$row['lot']})";

            $amount = (int) str_replace('.', '', $row['amount']);
            $paidAt = self::date($row['paid_at']);
            $period = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $row['period']) ? $row['period'] : null;

            $reason = match (true) {
                ! preg_match('/^\d{1,3}(\.\d{3})*$/', $row['amount']) || $amount <= 0 => 'nominal kosong',
                $paidAt === null => 'tanggal bayar tidak terbaca',
                $period === null => "periode \"{$row['period']}\" tidak terbaca",
                $period < self::START_PERIOD => 'periode sebelum '.self::START_PERIOD,
                default => null,
            };
            if ($reason) {
                self::$skipped[] = "{$label}: {$reason}";

                continue;
            }

            $unitId = self::unitId($units, $row['block'], $row['lot']);
            if ($unitId === null) {
                self::$skipped[] = "{$label}: blok/nomor tidak ada di pelanggan.csv";

                continue;
            }

            $method = strtolower($row['method']);
            $isCash = str_starts_with($method, 'tun') || str_starts_with($method, 'tua');

            $payments[] = [
                'line' => $line,
                'unit_id' => $unitId,
                'payer_name' => mb_substr($row['name'], 0, 100),
                'paid_at' => $paidAt->toDateString(),
                'amount' => $amount,
                'shares' => [$period => $amount],
                // Tunai dan debit (EDC) diterima di loket; transfer dan VA masuk ke rekening.
                'payment_method_id' => $isCash ? 'C' : 'D',
                'payment_channel_id' => $isCash || str_starts_with($method, 'debit') ? 'L' : 'M',
                'cashier_name' => $row['cashier'] === '' ? null : mb_substr(ucwords(strtolower($row['cashier'])), 0, 50),
                'notes' => self::notes($row),
            ];
        }

        usort($payments, fn (array $a, array $b) => [$a['paid_at'], $a['line']] <=> [$b['paid_at'], $b['line']]);

        return self::$payments = self::numberReceipts($payments);
    }

    /**
     * Baris sempelIpl.md yang tidak diimpor beserta alasannya (untuk ditampilkan seeder).
     *
     * @return array<int, string>
     */
    public static function skipped(): array
    {
        self::payments();

        return self::$skipped;
    }

    /**
     * Baris tabel transaksi di sempelIpl.md, di-key nomor baris file. Tabel lain (keterangan
     * kolom, ringkasan per bulan) dilewati karena header-nya tidak diawali Nama/Blok/No.
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

            $cells = array_map(fn ($cell) => trim(preg_replace('/\s+/', ' ', $cell)), explode('|', trim($text, '|')));

            if ($fields === null) {
                // Baris pertama tabel = header.
                $fields = array_slice($cells, 0, 3) === ['Nama', 'Blok', 'No.']
                    ? array_map(fn ($header) => self::COLUMNS[$header] ?? null, $cells)
                    : false;

                continue;
            }
            if ($fields === false || preg_match('/^:?-+:?$/', $cells[0])) {
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

    /**
     * Unit hasil impor pelanggan.csv di-key "BLOK|nomor" (nomor tanpa nol di depan).
     *
     * @return array<string, string>
     */
    private static function unitsByBlockLot(): array
    {
        $units = [];

        foreach (PelangganData::units() as $id => $unit) {
            $units[$unit['block'].'|'.self::lot($unit['lot_number'])] ??= $id;
        }

        return $units;
    }

    /** "8&9" atau "6,7,8,9" dicatat ke kavling pertama yang terdaftar. */
    private static function unitId(array $units, string $block, string $lots): ?string
    {
        $block = strtoupper(str_replace(' ', '', $block));
        $block = self::BLOCK_ALIASES[$block] ?? $block;

        foreach (preg_split('/\s*(?:&|,|\/|\bdan\b)\s*/i', $lots) as $lot) {
            if ($id = $units[$block.'|'.self::lot($lot)] ?? null) {
                return $id;
            }
        }

        return null;
    }

    private static function lot(string $lot): string
    {
        return ltrim(strtoupper(trim($lot)), '0');
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    private static function notes(array $row): string
    {
        $parts = ['Impor buku IPL manual.'];
        $parts[] = 'No. kwitansi manual: '.($row['receipt'] === '' ? '-' : $row['receipt']).'.';
        $parts[] = "Periode tertulis: {$row['period']}.";
        $parts[] = 'Cara bayar: '.trim("{$row['method']} {$row['pic']}").'.';

        if (! in_array($row['note'], ['', '-', '0'], true)) {
            $parts[] = "Keterangan: {$row['note']}.";
        }

        return implode(' ', $parts);
    }

    /**
     * Nomor kuitansi mengikuti format ReceiptService (PREFIX.Y.m.urutan6digit), diurutkan per
     * bulan tanggal bayar. Nomor kwitansi manual tidak dipakai langsung karena berulang setiap
     * tahun dan ada yang ganda/kosong; nomor aslinya tetap tercatat di catatan kuitansi.
     */
    private static function numberReceipts(array $payments): array
    {
        $prefix = config('grandduta.receipt_prefix', 'GD');
        $sequence = [];

        foreach ($payments as &$payment) {
            $base = $prefix.'.'.substr($payment['paid_at'], 0, 4).'.'.substr($payment['paid_at'], 5, 2).'.';
            $sequence[$base] = ($sequence[$base] ?? 0) + 1;
            $payment = ['receipt_number' => $base.str_pad((string) $sequence[$base], 6, '0', STR_PAD_LEFT)] + $payment;
        }

        return $payments;
    }
}
