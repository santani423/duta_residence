<?php

namespace Database\Seeders;

/**
 * Data pembayaran IPL dari buku pembayaran IPL docs/final_revisi_1.md (dibaca lewat PenghuniData),
 * seluruh buku dari baris pertama (2010) sampai terakhir. File itu satu-satunya sumber data
 * pembayaran IPL untuk seeder; pembayaran yang tidak tercatat di sana tidak diimpor.
 *
 * Kolom yang dipakai (dibaca dari header): Nama Penghuni, block, no unit ("8&9" untuk dua kavling),
 * NOMINAL ("112.500,00"), NML DBL, TGL Pembayaran, Bulan Tagihan (teks bebas, mis. "Mart-24",
 * "Nov-Des 23", "Des-23 - Feb-24"), NO. KWTANSI 1/2, Tunai/TF/VA, Proses (Loket/Online/Door To Door),
 * PIC (petugas), dan KET (1)/(2). Nominal satu kuitansi dibagi rata ke bulan-bulan yang dibayarnya.
 *
 * Setiap baris yang berisi uang dan menunjuk unit diimpor. Tanggal atau periode yang tidak bisa
 * dibaca diperkirakan, dan perkiraannya ditulis di catatan kuitansi. Yang tidak diimpor (lihat
 * skipped()): kuitansi batal, baris tanpa nominal, nominal yang hanya ada di kolom NML DBL, baris
 * identik, dan baris tanpa unit.
 */
class IplData
{
    public const SOURCE = PenghuniData::SOURCE;

    /**
     * Tagihan Belum Bayar (tunggakan) dihitung mulai periode ini. Sebelumnya hanya bulan yang
     * tercatat dibayar yang menjadi tagihan, karena buku tidak mencatat bulan yang tidak dibayar.
     */
    public const ARREARS_START_PERIOD = '2024-01';

    /**
     * Cluster yang IPL-nya ditagih lewat buku ini. Cluster D, G, J, AR, dan E (Dolomite, Garnet,
     * Jade, Amber, Emerald 48) tidak punya pembayaran lagi sejak 2017/2018, jadi tidak dibuatkan
     * tunggakan - hanya bulan yang memang tercatat dibayar.
     */
    public const BILLED_CLUSTERS = ['DD', 'AM', 'AL', 'JA', 'C', 'AX', 'RE', 'B', 'RD'];

    /** Seluruh buku (puluhan ribu kuitansi dan tagihan) tidak muat di limit bawaan CLI PHP (128M). */
    private const MEMORY_LIMIT = '1G';

    /** Rentang bulan terpanjang yang dianggap wajar untuk satu kuitansi. */
    private const MAX_MONTHS = 120;

    /**
     * Pembayaran dimuka lebih jauh dari ini (bulan setelah tanggal bayar) dianggap salah tulis. Yang
     * terjauh di buku dan nominalnya pas: "Jan'20-Des'23" Rp7.200.000 = 48 x Rp150.000.
     */
    private const MAX_MONTHS_AHEAD = 48;

    /**
     * Kuitansi batal/salah (kolom nama atau KET (1)). KET (2) tidak ikut karena "salah" di sana
     * menjelaskan tulisan pada kuitansi, bukan membatalkan pembayarannya.
     */
    private const CANCELLED = '/\b(cancel|cancle|batal|salah|system error|kwitansi ga ada)\b/i';

    /** Catatan di kolom Bulan Tagihan yang bukan nama bulan: "(6 bln)", "Cicilan Ke 2", "sisa 437.500". */
    private const PERIOD_NOISE = [
        '/\d{1,3}(?:[.,]\d{3})+|\d{5,}|\d+\s*(?:bln|bulan)\b|\d+\s*%/',
        '/\b(?:ipkl|ipl|cicilan|ke|bulan|bln|tagihan|kekurangan|pembayaran|penambahan|tambahan|pelunasan|periode|status|huni|belum|blm|tidak|tdk|tunggakan|lunas|sisa|total|utang|terakhir|kavling|sblmny|free|disk|charge|biaya|iii|ii|iv)\b/',
    ];

    /** Header tabel di final_revisi_1.md => nama field. */
    private const COLUMNS = [
        'Nama Penghuni' => 'name',
        'block' => 'block',
        'no unit' => 'lot',
        'NML DBL' => 'double_amount',
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

    /** Nilai config grandduta.ipl_seed_from_period saat $payments dibaca. */
    private static ?string $paymentsFrom = null;

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
        $from = config('grandduta.ipl_seed_from_period');

        if (self::$payments !== null && self::$paymentsFrom === $from) {
            return self::$payments;
        }

        self::$paymentsFrom = $from;
        self::$skipped = [];

        $units = self::unitsByKey();
        $paidDates = PenghuniData::paidDates();
        $previousPaidAt = PenghuniData::FIRST_YEAR.'-01-01';
        $payments = [];
        $seen = [];

        foreach (PenghuniData::rows(self::COLUMNS) as $line => $row) {
            $label = "baris {$line} ({$row['name']} {$row['block']}/{$row['lot']}, {$row['amount']}, \"{$row['period']}\")";
            $paidAt = $paidDates[$line];
            $estimates = [];

            // Tahun periode yang tidak jelas dibaca terhadap tanggal bayar, atau baris sebelumnya
            // bila kosong (bagian awal buku, 2010-2011, tidak mencatat tanggal bayar sama sekali).
            $periods = self::periods($row['period'], $paidAt ?: $previousPaidAt);

            if ($paidAt === '') {
                $paidAt = $periods === [] ? $previousPaidAt : "{$periods[0]}-01";
                $estimates[] = 'Tanggal bayar kosong/tidak terbaca, memakai '.($periods === [] ? 'tanggal baris sebelumnya' : 'awal periode').'.';
            }
            if ($periods === []) {
                $periods = [substr($paidAt, 0, 7)];
                $estimates[] = 'Periode tidak terbaca, dicatat ke bulan bayar.';
            }
            $previousPaidAt = $paidAt;

            $amount = self::amount($row['amount']);
            $unitId = self::unitId($units, $row['block'], $row['lot']);
            $signature = implode('|', $row);

            $reason = match (true) {
                $from !== null && end($periods) < $from => "periode sebelum {$from} (config grandduta.ipl_seed_from_period)",
                (bool) preg_match(self::CANCELLED, "{$row['name']} {$row['note']}") => 'kuitansi batal/salah',
                $amount <= 0 && preg_match('/^[\d.]+$/', $row['double_amount']) === 1 => 'nominal hanya di kolom NML DBL (kuitansi ganda)',
                $amount <= 0 => 'nominal kosong/nol',
                isset($seen[$signature]) => 'baris ganda (semua kolom sama)',
                $unitId === null => 'blok/nomor unit tidak terdaftar',
                default => null,
            };
            if ($reason) {
                self::skip($reason, $label);

                continue;
            }
            $seen[$signature] = true;

            if (preg_match('/^\d{1,3}$/', $row['amount'])) {
                $estimates[] = "Nominal tertulis \"{$row['amount']}\", dibaca dalam ribuan.";
            }

            $share = intdiv($amount, count($periods));
            $shares = array_fill_keys($periods, $share);
            $shares[end($periods)] += $amount - $share * count($periods);

            $method = strtolower($row['method']);
            // Cara bayar yang tidak tercatat mengikuti default kuitansi: tunai di loket.
            $isCash = $method === '' || str_starts_with($method, 'tun') || str_starts_with($method, 'tua') || str_starts_with($method, 'cash');

            $payments[] = [
                'line' => $line,
                'unit_id' => $unitId,
                'payer_name' => mb_substr(preg_replace('/\s+/', ' ', $row['name']), 0, 100),
                'paid_at' => $paidAt,
                'amount' => $amount,
                'shares' => $shares,
                // Tunai dan debit (EDC) diterima di loket; transfer dan VA masuk ke rekening.
                'payment_method_id' => $isCash ? 'C' : 'D',
                'payment_channel_id' => $isCash || str_starts_with($method, 'debit') || str_starts_with($method, 'debet') ? 'L' : 'M',
                'cashier_name' => $row['cashier'] === '' ? null : mb_substr(ucwords(strtolower($row['cashier'])), 0, 50),
                'notes' => self::notes($row, $estimates),
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

    /** Naikkan memory_limit bila kurang untuk seluruh buku; limit yang sudah lebih besar tidak diubah. */
    public static function ensureMemoryLimit(): void
    {
        $current = ini_parse_quantity(ini_get('memory_limit'));

        if ($current !== -1 && $current < ini_parse_quantity(self::MEMORY_LIMIT)) {
            ini_set('memory_limit', self::MEMORY_LIMIT);
        }
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
     * ("Nov-Feb 24" = Nov 2023 s/d Feb 2024), atau tahun terdekat dengan $reference (tanggal
     * bayar) bila tidak ada bulan sesudahnya. Rentang terbalik karena tahunnya salah tulis
     * ("Des'18 - Jan'18") dibaca sebagai rentang yang berakhir paling dekat dengan $reference.
     *
     * @return list<string>
     */
    public static function periods(string $text, string $reference): array
    {
        $text = strtolower(trim($text));
        $paid = self::monthIndex($reference);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)) {
            // Tanggal hasil konversi: pilih tafsiran (tahun tertulis, atau hari = 2 digit tahun) yang terdekat dengan tanggal bayar.
            $candidates = [self::monthIndex("{$m[1]}-{$m[2]}")];
            if ((int) $m[1] === PenghuniData::CONVERTED_YEAR) {
                $candidates[] = $m[3] >= 10 && $m[3] <= PenghuniData::CONVERTED_YEAR % 100
                    ? self::monthIndex("20{$m[3]}-{$m[2]}")
                    : self::nearestMonth((int) $m[2], $paid);
            }
            usort($candidates, fn (int $a, int $b) => abs($a - $paid) <=> abs($b - $paid));
            $ranges = [[$candidates[0], $candidates[0]]];
        } else {
            $text = self::cleanPeriodText($text);

            // Kata selain nama bulan (mis. "Cek pembayaran") membuat periode dianggap tidak terbaca.
            preg_match_all('/[a-z]{3,}/', $text, $words);
            foreach ($words[0] as $word) {
                if (! isset(PenghuniData::MONTHS[substr($word, 0, 3)]) && $word !== 'dan') {
                    return [];
                }
            }

            $tokens = [];
            foreach (preg_split('/\s*(?:\bdan\b|&|,|\+|\/)\s*/', $text) as $group => $segment) {
                preg_match_all('/([a-z]{3})[a-z]*[\s.\-\'"=;]*(\d+)?/', $segment, $found, PREG_SET_ORDER);
                foreach ($found as $match) {
                    if (isset(PenghuniData::MONTHS[$match[1]])) {
                        $tokens[] = ['group' => $group, 'month' => PenghuniData::MONTHS[$match[1]], 'year' => $match[2] ?? ''];
                    }
                }
            }

            $next = null;
            for ($i = count($tokens) - 1; $i >= 0; $i--) {
                // Tahun selain 2 atau 4 digit salah ketik ("Apr'2", "DES'222"), diperlakukan seperti tidak ditulis.
                $year = match (strlen($tokens[$i]['year'])) {
                    2 => 2000 + (int) $tokens[$i]['year'],
                    4 => (int) $tokens[$i]['year'],
                    default => null,
                };

                if ($year !== null) {
                    $next = $tokens[$i]['index'] = $year * 12 + $tokens[$i]['month'] - 1;
                } elseif ($next !== null) {
                    $index = intdiv($next, 12) * 12 + $tokens[$i]['month'] - 1;
                    $next = $tokens[$i]['index'] = $index > $next ? $index - 12 : $index;
                } else {
                    $next = $tokens[$i]['index'] = self::nearestMonth($tokens[$i]['month'], $paid);
                }
            }

            $ranges = [];
            foreach ($tokens as $token) {
                $ranges[$token['group']] = [$ranges[$token['group']][0] ?? $token['index'], $token['index']];
            }
        }

        $months = [];
        foreach ($ranges as [$from, $to]) {
            if ($from > $to && $from - $to < 12) {
                abs($to - $paid) <= abs($to + 12 - $paid) ? $from -= 12 : $to += 12;
            }
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

    /**
     * Buang catatan dari teks Bulan Tagihan sehingga tersisa bulan dan tahun. Bulan sebelum "(Free)"
     * tidak ditagih, jadi nominalnya hanya untuk bulan sesudahnya. Isi kurung yang menyebut bulan dan
     * tahun dibaca sebagai rentang tersendiri ("Feb'20 ( Jan'19 - May'19)"); kurung lainnya hanya
     * catatan, dan rentangnya berlanjut bila diikuti "-" ("Agustus 2013( Blm Huni )-Maret 2014").
     */
    private static function cleanPeriodText(string $text): string
    {
        $text = preg_replace('/^.*\(\s*free\s*\)\W*(?=[a-z])/', '', $text);
        $text = preg_replace_callback('/\(([^)]*)\)(\s*-)?/', function (array $m) {
            preg_match_all('/([a-z]{3})[a-z]*[\s\'-]*\d{2}/', $m[1], $found);

            return match (true) {
                array_intersect($found[1], array_keys(PenghuniData::MONTHS)) !== [] => " , {$m[1]} , ",
                isset($m[2]) => ' - ',
                default => ' , ',
            };
        }, $text);
        $text = preg_replace('/\bs\s*\/\s*d\s*|\bsampai\b/', ' - ', $text);

        return preg_replace(self::PERIOD_NOISE, ' ', $text);
    }

    /** Bulan (1-12) pada tahun yang paling dekat dengan nomor bulan $reference. */
    private static function nearestMonth(int $month, int $reference): int
    {
        return (int) round(($reference - $month + 1) / 12) * 12 + $month - 1;
    }

    /** "YYYY-MM..." => nomor bulan berurutan (tahun * 12 + bulan - 1). */
    private static function monthIndex(string $period): int
    {
        return (int) substr($period, 0, 4) * 12 + (int) substr($period, 5, 2) - 1;
    }

    /** "112.500,00", "1.650.000", "Rp500.000,00", atau "225" (ditulis dalam ribuan) => rupiah; 0 bila tidak terbaca. */
    private static function amount(string $value): int
    {
        $value = str_ireplace(['rp', ' '], '', $value);

        return match (true) {
            preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $value) === 1 => (int) str_replace('.', '', explode(',', $value)[0]),
            preg_match('/^\d{1,3}$/', $value) === 1 => (int) $value * 1000,
            default => 0,
        };
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

    /** @param list<string> $estimates nilai yang diperkirakan karena tidak terbaca di buku */
    private static function notes(array $row, array $estimates): string
    {
        $receipts = array_filter([$row['receipt_book'], $row['receipt']], fn (string $value) => ! in_array($value, ['', '-', '0'], true));

        $parts = ['Impor buku IPL manual.'];
        $parts[] = 'No. kwitansi manual: '.($receipts ? implode(' / ', $receipts) : '-').'.';
        $parts[] = "Periode tertulis: {$row['period']}.";
        $parts[] = 'Tanggal bayar tertulis: '.($row['paid_at'] ?: '-').'.';
        $parts[] = 'Cara bayar: '.(trim("{$row['method']} {$row['process']}") ?: 'tidak tercatat').'.';

        foreach ([$row['note'], $row['note2']] as $note) {
            if (! in_array($note, ['', '-', '0'], true)) {
                $parts[] = "Keterangan: {$note}.";
            }
        }

        return implode(' ', [...$parts, ...$estimates]);
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
