<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * User ber-role `collector` yang belum dihapus (boleh non-aktif). Dipakai untuk filter, target,
 * dan laporan — bukan untuk penugasan baru (pakai ActiveCollector).
 */
class CollectorUser implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (int) $value != $value || (int) $value <= 0) {
            $fail('Kolektor tidak valid.');

            return;
        }

        if (! self::constrain(User::query())->whereKey((int) $value)->exists()) {
            $fail('Kolektor tidak ditemukan.');
        }
    }

    /** Batasi query User ke user ber-role collector (soft-deleted otomatis terkecuali). */
    public static function constrain(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'collector'));
    }
}
