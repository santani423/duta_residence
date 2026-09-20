<?php

namespace App\Support;

/** Angka -> kata dalam bahasa Indonesia, untuk baris "Terbilang" pada kuitansi. */
class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(float|int|string $amount): string
    {
        $whole = (int) round((float) $amount);

        $words = $whole === 0 ? 'nol' : preg_replace('/\s+/', ' ', trim(self::words($whole)));

        return ucfirst($words).' rupiah';
    }

    private static function words(int $n): string
    {
        return match (true) {
            $n === 0 => '',
            $n < 12 => self::UNITS[$n],
            $n < 20 => self::UNITS[$n - 10].' belas',
            $n < 100 => self::UNITS[intdiv($n, 10)].' puluh '.self::UNITS[$n % 10],
            $n < 200 => 'seratus '.self::words($n - 100),
            $n < 1000 => self::UNITS[intdiv($n, 100)].' ratus '.self::words($n % 100),
            $n < 2000 => 'seribu '.self::words($n - 1000),
            $n < 1000000 => self::words(intdiv($n, 1000)).' ribu '.self::words($n % 1000),
            $n < 1000000000 => self::words(intdiv($n, 1000000)).' juta '.self::words($n % 1000000),
            $n < 1000000000000 => self::words(intdiv($n, 1000000000)).' miliar '.self::words($n % 1000000000),
            default => self::words(intdiv($n, 1000000000000)).' triliun '.self::words($n % 1000000000000),
        };
    }
}
