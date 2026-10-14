<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;

/**
 * Data pembayaran IPL dari buku IPL manual (worksheet "ALL" pada "IPKL Manual 2017 UPTODATE",
 * lihat docs/sempelIpl.md) yang menyangkut IPL 2024 sampai data terbaru: database/data/ipl.csv.
 *
 * Format kolom (tanpa header), urutan sama dengan worksheet aslinya:
 *  0 nama pembayar, 1 blok, 2 nomor ("8&9" untuk dua kavling), 3 nominal, 4 tanggal bayar
 *  (ISO, atau "02-05-024"), 5 periode yang dibayar (teks bebas: "2024-01-01T00:00:00", "Okt 26",
 *  "Nov-23 - Feb-24", "Jan-Des 24", "Ags-Nov 23 dan Feb-Mart 24", ...), 6 no. kwitansi manual,
 *  7 keterangan tambahan, 8 cara bayar (Tunai/Transfer/VA/Debit), 9 PIC (Loket/Online),
 *  10 petugas, 11 catatan.
 *
 * Yang diimpor hanya pembayaran bertanggal mulai 2024, ditambah pembayaran dimuka akhir 2023 yang
 * melunasi periode 2024 ke atas - tanpa itu unit yang sudah bayar dimuka akan terlihat menunggak.
 */
class IplData
{
    public const CSV = 'data/ipl.csv';

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

    /** Nama bulan (termasuk salah ketik yang ada di buku) => nomor bulan. */
    private const MONTHS = [
        'jan' => 1, 'januari' => 1,
        'feb' => 2, 'peb' => 2, 'februari' => 2,
        'mar' => 3, 'mart' => 3, 'maret' => 3, 'mret' => 3,
        'apr' => 4, 'aprl' => 4, 'april' => 4, 'aprrl' => 4, 'aprli' => 4, 'apel' => 4,
        'mei' => 5, 'mey' => 5,
        'jun' => 6, 'juni' => 6,
        'jul' => 7, 'juli' => 7,
        'ags' => 8, 'agt' => 8, 'agu' => 8, 'aug' => 8, 'agustus' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'ok' => 10, 'otk' => 10, 'oktober' => 10,
        'nov' => 11, 'nop' => 11, 'ov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12,
    ];

    private static ?array $payments = null;

    /** @var array<int, string> */
    private static array $skipped = [];

    /**
     * Pembayaran hasil impor, urut tanggal bayar. Nominal dibagi ke setiap periode yang dibayar
     * sebanding tarif cluster periode itu, supaya rentang yang melewati kenaikan tarif terbagi
     * benar (sisa pembulatan masuk ke periode terakhir).
     *
     * @return array<int, array{line: int, receipt_number: string, unit_id: string, payer_name: string, paid_at: string, amount: int, shares: array<string, int>, payment_method_id: string, payment_channel_id: string, cashier_name: ?string, notes: string}>
     */
    public static function payments(): array
    {
        if (self::$payments !== null) {
            return self::$payments;
        }

        $units = self::unitsByBlockLot();
        $clusterIds = array_column(PelangganData::units(), 'cluster_id', 'id');
        $start = CarbonImmutable::parse(self::START_PERIOD.'-01');
        $handle = fopen(database_path(self::CSV), 'r');
        $payments = [];
        $line = 0;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            $row = array_pad(array_map(fn ($value) => trim(preg_replace('/\s+/', ' ', (string) $value)), $row), 12, '');
            $label = "baris {$line} ({$row[0]} {$row[1]}/{$row[2]})";

            $paidAt = self::date($row[4]);
            $periods = self::parsePeriods($row[5], $paidAt);
            $amount = ctype_digit($row[3]) ? (int) $row[3] : 0;

            $reason = match (true) {
                $amount <= 0 => 'nominal kosong',
                $paidAt === null => 'tanggal bayar tidak terbaca',
                $periods === null => "periode \"{$row[5]}\" tidak terbaca",
                default => null,
            };
            if ($reason) {
                self::$skipped[] = "{$label}: {$reason}";

                continue;
            }

            if ($paidAt->lt($start) && end($periods) < self::START_PERIOD) {
                continue; // Di luar cakupan: dibayar sebelum 2024 untuk periode sebelum 2024.
            }

            $unitId = self::unitId($units, $row[1], $row[2]);
            if ($unitId === null) {
                self::$skipped[] = "{$label}: blok/nomor tidak ada di pelanggan.csv";

                continue;
            }

            $method = strtolower($row[8]);
            $isCash = str_starts_with($method, 'tun') || str_starts_with($method, 'tua');

            $payments[] = [
                'line' => $line,
                'unit_id' => $unitId,
                'payer_name' => mb_substr($row[0], 0, 100),
                'paid_at' => $paidAt->toDateString(),
                'amount' => $amount,
                'shares' => self::shares($amount, $periods, $clusterIds[$unitId]),
                // Tunai dan debit (EDC) diterima di loket; transfer dan VA masuk ke rekening.
                'payment_method_id' => $isCash ? 'C' : 'D',
                'payment_channel_id' => $isCash || str_starts_with($method, 'debit') ? 'L' : 'M',
                'cashier_name' => $row[10] === '' ? null : mb_substr(ucwords(strtolower($row[10])), 0, 50),
                'notes' => self::notes($row),
            ];
        }

        fclose($handle);

        usort($payments, fn (array $a, array $b) => [$a['paid_at'], $a['line']] <=> [$b['paid_at'], $b['line']]);

        return self::$payments = self::numberReceipts($payments);
    }

    /**
     * Baris ipl.csv yang tidak diimpor beserta alasannya (untuk ditampilkan seeder).
     *
     * @return array<int, string>
     */
    public static function skipped(): array
    {
        self::payments();

        return self::$skipped;
    }

    /**
     * Periode (format "Y-m", urut naik) dari teks kolom PEMBAYARAN, atau null bila tidak terbaca.
     * Tahun yang tidak ditulis ("April-Jun") diambil dari tahun tanggal bayar.
     *
     * @return array<int, string>|null
     */
    public static function parsePeriods(string $text, ?CarbonImmutable $paidAt = null): ?array
    {
        $text = strtolower(trim($text));

        if (preg_match('/^(\d{4})-(\d{2})-\d{2}t[\d:]+$/', $text, $m)) {
            return [sprintf('%04d-%02d', $m[1], $m[2])];
        }

        $periods = [];

        foreach (preg_split('/\s+dan\s+/', str_replace('=', '-', $text)) as $part) {
            preg_match_all('/[a-z]+|\d+/', $part, $tokens);
            $sequence = []; // [[bulan, tahun|null], ...]

            foreach ($tokens[0] as $token) {
                if (! ctype_digit($token)) {
                    if (! isset(self::MONTHS[$token])) {
                        return null;
                    }
                    $sequence[] = [self::MONTHS[$token], null];

                    continue;
                }

                // Tahun berlaku untuk semua bulan sebelumnya yang belum bertahun ("Nov-Des 23").
                $year = self::year($token, $paidAt);
                if ($year === null || $sequence === [] || end($sequence)[1] !== null) {
                    return null;
                }
                foreach ($sequence as &$item) {
                    $item[1] ??= $year;
                }
                unset($item);
            }

            if ($sequence === []) {
                return null;
            }
            if (end($sequence)[1] === null) {
                if ($paidAt === null) {
                    return null;
                }
                foreach ($sequence as &$item) {
                    $item[1] ??= $paidAt->year;
                }
                unset($item);
            }

            [$fromMonth, $fromYear] = $sequence[0];
            [$toMonth, $toYear] = end($sequence);
            // "Nov-Jan 24" = November tahun sebelumnya sampai Januari 2024.
            if ($fromYear === $toYear && $fromMonth > $toMonth) {
                $fromYear--;
            }

            $from = CarbonImmutable::create($fromYear, $fromMonth, 1);
            $to = CarbonImmutable::create($toYear, $toMonth, 1);
            if ($from->gt($to) || $from->diffInMonths($to) > 120) {
                return null;
            }

            for ($period = $from; $period->lte($to); $period = $period->addMonth()) {
                $periods[] = $period->format('Y-m');
            }
        }

        $periods = array_values(array_unique($periods));
        sort($periods);

        return $periods;
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
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:T[\d:]+)?$/', $value, $m)) {
            [, $year, $month, $day] = $m;
        } elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{2,4})$/', $value, $m)) {
            // Tanggal ketikan manual, mis. "02-05-024" = 2 Mei 2024.
            [, $day, $month, $year] = $m;
            $year = strlen($year) === 4 ? (int) $year : 2000 + (int) $year;
        } else {
            return null;
        }

        return checkdate((int) $month, (int) $day, (int) $year) ? CarbonImmutable::create((int) $year, (int) $month, (int) $day) : null;
    }

    /** "24" => 2024, "024" => 2024, "4" => 2024 (dekade tanggal bayar). */
    private static function year(string $token, ?CarbonImmutable $paidAt): ?int
    {
        $year = match (strlen($token)) {
            1 => intdiv($paidAt?->year ?? 2020, 10) * 10 + (int) $token,
            2, 3 => 2000 + (int) $token,
            4 => (int) $token,
            default => null,
        };

        return $year !== null && $year >= 2000 && $year <= 2100 ? $year : null;
    }

    /**
     * @param  array<int, string>  $periods
     * @return array<string, int>
     */
    private static function shares(int $amount, array $periods, string $clusterId): array
    {
        $weights = array_map(fn (string $period) => self::tariff($clusterId, $period), array_combine($periods, $periods));
        $total = array_sum($weights);
        $shares = array_map(fn (int $weight) => intdiv($amount * $weight, $total), $weights);
        $shares[end($periods)] += $amount - array_sum($shares);

        return $shares;
    }

    /** Tarif cluster pada suatu periode menurut clusters.csv dan EstateSeeder::RATE_INCREASES. */
    private static function tariff(string $clusterId, string $period): int
    {
        static $rates;
        $rates ??= array_column(EstateSeeder::clusters(), 'rate', 'id');
        [$increaseDate, $previousRate] = EstateSeeder::RATE_INCREASES[$clusterId] ?? [null, null];

        return $increaseDate !== null && $period < substr($increaseDate, 0, 7) ? $previousRate : max(1, $rates[$clusterId]);
    }

    private static function notes(array $row): string
    {
        $parts = ['Impor buku IPL manual.'];
        $parts[] = 'No. kwitansi manual: '.($row[6] === '' ? '-' : $row[6]).'.';
        $parts[] = "Periode tertulis: {$row[5]}.";
        $parts[] = 'Cara bayar: '.trim("{$row[8]} {$row[9]}").'.';

        foreach ([$row[7], $row[11]] as $extra) {
            if (! in_array($extra, ['', '-', '0'], true)) {
                $parts[] = "Keterangan: {$extra}.";
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
