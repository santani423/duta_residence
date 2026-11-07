<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

class LandingContentCache
{
    // v2: payload disimpan sebagai array JSON murni; entri v1 berisi model Eloquent.
    public const KEY = 'landing:content:v2';

    /**
     * Payload dinormalisasi ke bentuk JSON-nya (array skalar) sebelum di-cache, karena
     * `cache.serializable_classes = false` menolak unserialize objek apa pun dari cache.
     * Output respons identik dengan men-serialize model secara langsung.
     */
    public static function remember(Closure $builder): array
    {
        return Cache::rememberForever(self::KEY, fn () => json_decode(
            json_encode($builder(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR
        ));
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
