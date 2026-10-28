<?php

namespace Database\Seeders;

/**
 * Data pembayaran IPL dari buku pembayaran IPL docs/final_revisi_1.md (dibaca lewat PenghuniData).
 * File itu satu-satunya sumber data pembayaran IPL untuk seeder; pembayaran yang tidak tercatat di
 * sana tidak diimpor.
 *
 * Kolom yang dipakai (dibaca dari header): Nama Penghuni, block, no unit ("8&9" untuk dua kavling),
 * NOMINAL ("112.500,00"), TGL Pembayaran, Bulan Tagihan (teks bebas, mis. "Mart-24", "Nov-Des 23",
 * "Des-23 - Feb-24"), NO. KWTANSI 1/2, Tunai/TF/VA, Proses (Loket/Online/Door To Door), PIC
 * (petugas), dan KET (1)/(2). Nominal satu kuitansi dibagi rata ke bulan-bulan yang dibayarnya.
 */
class IplData
{
    public const SOURCE = PenghuniData::SOURCE;

    /** Periode IPL pertama yang dicakup data ini. */
    public const START_PERIOD = '2024-01';

    /**
     * Cluster yang IPL-nya ditagih lewat buku ini. Cluster 01-04 dan 10 (Dolomite, Garnet, Jade,
     * Amber, Emerald 48) tidak punya pembayaran lagi sejak 2017/2018, jadi tidak dibuatkan
     * tunggakan - hanya bulan yang memang tercatat dibayar.
     */
    public const BILLED_CLUSTERS = ['05', '06', '07', '08', '09', '11', '12', '13', '14'];

    /**
     * Tahun saat buku dikonversi. Teks "Des-23" di kolom Bulan Tagihan ada yang terbaca sebagai
     * tanggal 23 Desember tahun ini ("2026-12-23"), jadi hari pada tanggal tahun ini bisa berarti tahun.
     */
    private const CONVERTED_YEAR = '2026';

    /** Rentang bulan terpanjang yang dianggap wajar untuk satu kuitansi. */
    private const MAX_MONTHS = 120;

    /** Pembayaran dimuka lebih jauh dari ini (bulan setelah tanggal bayar) dianggap salah tulis. */
    private const MAX_MONTHS_AHEAD = 24;

    /** Kuitansi batal/salah (kolom nama atau keterangan). */
    private const CANCELLED = '/\b(cancel|cancle|batal|salah|system error|kwitansi ga ada)\b/i';

    /** Header tabel di final_revisi_1.md => nama field. */
    private const COLUMNS = [
        'Nama Penghuni' => 'name',
        'block' => 'block',
        'no unit' => 'lot',
        'NOMINAL' => 'amount',
        'TGL Pembayaran' => 'paid_at',
        'Bulan Tagihan' => 'period',
        'NO. KWTANSI 1' => 'receipt_book',
        'NO. KWTANSI 2' => 'receipt',
        'Tunai/TF/VA' => 'method',
        'Proses' => 'process',
        'PIC' => 'cashier',
        'KET (1)' => 'note',
        'KET (2)' => 'note2',
    ];

    private static ?array $payments = null;

    /** @var array<string, array{count: int, examples: list<string>}> alasan => jumlah baris + contoh */
    private static array $skipped = [];

    /**
     * Pembayaran hasil impor, urut tanggal bayar. shares berisi bulan yang dibayar (YYYY-MM) dengan
     * bagian nominalnya; sisa pembagian masuk ke bulan terakhir.
     *
     * @return array<int, array{line: int, receipt_number: string, unit_id: string, payer_name: string, paid_at: string, amount: int, shares: array<string, int>, payment_method_id: string, payment_channel_id: string, cashier_name: ?string, notes: string}>
     */
    public static function payments(): array
    {
        if (self::$payments !== null) {
            return self::$payments;
        }

        $units = self::unitsByKey();
        $oldestPaidAt = self::monthIndex(self::START_PERIOD) - self::MAX_MONTHS_AHEAD;
        $payments = [];
        $seen = [];

        foreach (PenghuniData::rows(self::COLUMNS) as $line => $row) {
            $label = "baris {$line} ({$row['name']} {$row['block']}/{$row['lot']}, {$row['amount']}, \"{$row['period']}\")";
            $paidAt = PenghuniData::date($row['paid_at']);

            // Baris lama dihitung tanpa contoh supaya laporan fokus ke data periode berjalan.
            if ($paidAt === '') {
                self::skip('tanggal bayar kosong/tidak terbaca', null);

                continue;
            }
            if (self::monthIndex($paidAt) < $oldestPaidAt) {
                self::skip('dibayar sebelum '.self::START_PERIOD, null);

                continue;
            }

            $amount = self::amount($row['amount']);
            $periods = self::periods($row['period'], $paidAt);
            $unitId = self::unitId($units, $row['block'], $row['lot']);
            $signature = implode('|', $row);

            $reason = match (true) {
                $periods === [] => 'periode tidak terbaca',
                end($periods) < self::START_PERIOD => 'periode sebelum '.self::START_PERIOD,
                (bool) preg_match(self::CANCELLED, "{$row['name']} {$row['note']} {$row['note2']}") => 'kuitansi batal/salah',
                $amount <= 0 => 'nominal kosong/tidak terbaca',
                isset($seen[$signature]) => 'baris ganda (semua kolom sama)',
                $unitId === null => 'blok/nomor unit tidak terdaftar',
                default => null,
            };
            if ($reason) {
                self::skip($reason, $label);

                continue;
            }
            $seen[$signature] = true;

            $share = intdiv($amount, count($periods));
            $shares = array_fill_keys($periods, $share);
            $shares[end($periods)] += $amount - $share * count($periods);

            $method = strtolower($row['method']);
            $isCash = str_starts_with($method, 'tun') || str_starts_with($method, 'tua');

            $payments[] = [
                'line' => $line,
                'unit_id' => $unitId,
                'payer_name' => mb_substr(preg_replace('/\s+/', ' ', $row['name']), 0, 100),
                'paid_at' => $paidAt,
                'amount' => $amount,
                'shares' => $shares,
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
     * Ringkasan baris buku IPL yang tidak diimpor, satu baris per alasan (untuk ditampilkan seeder).
     *
     * @return array<int, string>
     */
    public static function skipped(): array
    {
        self::payments();

        return array_map(
            fn (string $reason, array $info) => "{$reason}: {$info['count']} baris".($info['examples'] ? ' (mis. '.implode('; ', $info['examples']).')' : ''),
            array_keys(self::$skipped),
            self::$skipped,
        );
    }

    private static function skip(string $reason, ?string $example): void
    {
        self::$skipped[$reason] ??= ['count' => 0, 'examples' => []];
        self::$skipped[$reason]['count']++;

        if ($example !== null && count(self::$skipped[$reason]['examples']) < 5) {
            self::$skipped[$reason]['examples'][] = $example;
        }
    }

    /**
     * Bulan yang dibayar (YYYY-MM, urut) dari teks Bulan Tagihan; kosong bila tidak terbaca.
     * "Mart-24" = satu bulan, "Nov-Des 23" / "Des-23 - Feb-24" = rentang, "Ags-Nov 23 dan
     * Feb-Mart 24" = beberapa rentang. Tahun yang tidak ditulis mengikuti bulan sesudahnya
     * ("Nov-Feb 24" = Nov 2023 s/d Feb 2024).
     *
     * @return list<string>
     */
    private static function periods(string $text, string $paidAt): array
    {
        $text = strtolower(trim($text));
        $paid = self::monthIndex($paidAt);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)) {
            // Tanggal hasil konversi: pilih tafsiran (tahun tertulis, atau hari = 2 digit tahun) yang terdekat dengan tanggal bayar.
            $candidates = [self::monthIndex("{$m[1]}-{$m[2]}")];
            if ($m[1] === self::CONVERTED_YEAR) {
                $candidates[] = self::monthIndex("20{$m[3]}-{$m[2]}");
            }
            usort($candidates, fn (int $a, int $b) => abs($a - $paid) <=> abs($b - $paid));
            $ranges = [[$candidates[0], $candidates[0]]];
        } else {
            // Kata selain nama bulan (mis. salah ketik "Maet") membuat periode dianggap tidak terbaca.
            preg_match_all('/[a-z]{3,}/', $text, $words);
            foreach ($words[0] as $word) {
                if (! isset(PenghuniData::MONTHS[substr($word, 0, 3)]) && ! in_array($word, ['dan', 'sampai'], true)) {
                    return [];
                }
            }

            $tokens = [];
            foreach (preg_split('/\s*(?:\bdan\b|&|,|\+)\s*/', $text) as $group => $segment) {
                preg_match_all('/([a-z]{3})[a-z]*\.?\s*[-\'=]?\s*(\d{4}|\d{2})?(?!\d)/', $segment, $found, PREG_SET_ORDER);
                foreach ($found as $match) {
                    if (isset(PenghuniData::MONTHS[$match[1]])) {
                        $tokens[] = ['group' => $group, 'month' => PenghuniData::MONTHS[$match[1]], 'year' => $match[2] ?? ''];
                    }
                }
            }

            $next = null;
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                if ($tokens[$i]['year'] !== '') {
                    $year = strlen($tokens[$i]['year']) === 2 ? 2000 + (int) $tokens[$i]['year'] : (int) $tokens[$i]['year'];
                    $next = $tokens[$i]['index'] = $year * 12 + $tokens[$i]['month'] - 1;
                } elseif ($next !== null) {
                    $index = intdiv($next, 12) * 12 + $tokens[$i]['month'] - 1;
                    $next = $tokens[$i]['index'] = $index > $next ? $index - 12 : $index;
                } else {
                    return [];
                }
            }

            $ranges = [];
            foreach ($tokens as $token) {
                $ranges[$token['group']] = [$ranges[$token['group']][0] ?? $token['index'], $token['index']];
            }
        }

        $months = [];
        foreach ($ranges as [$from, $to]) {
            if ($from > $to || $to - $from >= self::MAX_MONTHS || $to > $paid + self::MAX_MONTHS_AHEAD) {
                return [];
            }
            for ($i = $from; $i <= $to; $i++) {
                $months[$i] = sprintf('%04d-%02d', intdiv($i, 12), $i % 12 + 1);
            }
        }
        ksort($months);

        return array_values($months);
    }

    /** "YYYY-MM..." => nomor bulan berurutan (tahun * 12 + bulan - 1). */
    private static function monthIndex(string $period): int
    {
        return (int) substr($period, 0, 4) * 12 + (int) substr($period, 5, 2) - 1;
    }

    /** "112.500,00", "1.650.000", atau "Rp500.000,00" => rupiah; 0 bila tidak terbaca. */
    private static function amount(string $value): int
    {
        $value = str_ireplace(['rp', ' '], '', $value);

        return preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value) ? (int) str_replace('.', '', explode(',', $value)[0]) : 0;
    }

    /**
     * Kode unit di-key PenghuniData::key() (blok + nomor tanpa nol di depan).
     *
     * @return array<string, string>
     */
    private static function unitsByKey(): array
    {
        $units = [];

        foreach (PelangganData::units() as $unit) {
            $units[PenghuniData::key($unit['block'], $unit['lot_number'])] ??= (string) $unit['id'];
        }

        return $units;
    }

    /** "8&9" atau "6,7,8,9" dicatat ke kavling pertama yang terdaftar. */
    private static function unitId(array $units, string $block, string $lots): ?string
    {
        foreach (preg_split('/\s*(?:&|,|\/|\bdan\b)\s*/i', $lots) as $lot) {
            if ($id = $units[PenghuniData::key($block, $lot)] ?? null) {
                return $id;
            }
        }

        return null;
    }

    private static function notes(array $row): string
    {
        $receipts = array_filter([$row['receipt_book'], $row['receipt']], fn (string $value) => ! in_array($value, ['', '-', '0'], true));

        $parts = ['Impor buku IPL manual.'];
        $parts[] = 'No. kwitansi manual: '.($receipts ? implode(' / ', $receipts) : '-').'.';
        $parts[] = "Periode tertulis: {$row['period']}.";
        $parts[] = 'Cara bayar: '.trim("{$row['method']} {$row['process']}").'.';

        foreach ([$row['note'], $row['note2']] as $note) {
            if (! in_array($note, ['', '-', '0'], true)) {
                $parts[] = "Keterangan: {$note}.";
            }
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
