<?php

namespace App\Support;

/**
 * Aturan dan normalisasi "Nama Pembayar" - pihak yang membayar tagihan, yang belum tentu pemilik unit.
 * Field ini opsional: kosong disimpan sebagai NULL, dan tidak pernah diisi otomatis dengan nama pemilik unit.
 */
final class PayerName
{
    public const MAX_LENGTH = 150;

    /** @return array<int, string> */
    public static function rules(): array
    {
        return ['nullable', 'string', 'max:'.self::MAX_LENGTH];
    }

    /** Rapikan input: buang spasi berlebih; string kosong atau bukan string menjadi null. */
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $clean === '' ? null : $clean;
    }
}
