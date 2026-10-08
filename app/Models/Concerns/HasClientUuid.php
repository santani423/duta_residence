<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * UUID yang dibuat oleh klien (aplikasi collector) saat aksi dicatat, termasuk saat offline.
 * Kolom `client_uuid` unik, sehingga item yang terkirim ulang tidak pernah tercatat dua kali.
 */
trait HasClientUuid
{
    public function scopeForClientUuid(Builder $query, ?string $clientUuid): Builder
    {
        return $query->where('client_uuid', $clientUuid);
    }

    public static function findByClientUuid(?string $clientUuid): ?static
    {
        return blank($clientUuid) ? null : static::query()->forClientUuid($clientUuid)->first();
    }
}
