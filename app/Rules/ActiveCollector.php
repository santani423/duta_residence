<?php

namespace App\Rules;

use App\Models\CollectorProfile;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Collector aktif = user ber-role `collector`, `is_active = true`, tidak soft-deleted, dan
 * profil collector berstatus `active`. Dipakai untuk setiap input yang MENUGASKAN pekerjaan
 * (collector_id, new_collector_id, reassign_to_collector_id). Collector lama tanpa baris
 * profil (dibuat sebelum modul profil) tetap dianggap aktif selama `is_active = true`.
 */
class ActiveCollector implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || (int) $value != $value || (int) $value <= 0) {
            $fail('Kolektor tidak valid.');

            return;
        }

        if (! self::constrain(User::query())->whereKey((int) $value)->exists()) {
            $fail('Kolektor tidak ditemukan atau tidak aktif.');
        }
    }

    /** Batasi query User ke collector aktif (definisi yang sama dengan rule ini). */
    public static function constrain(Builder $query): Builder
    {
        return CollectorUser::constrain($query)
            ->where('users.is_active', true)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('collectorProfile')
                ->orWhereHas('collectorProfile', fn (Builder $p) => $p->where('account_status', CollectorProfile::STATUS_ACTIVE)));
    }
}
