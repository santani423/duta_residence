<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

class LandingContentCache
{
    public const KEY = 'landing:content:v1';

    public static function remember(Closure $builder): array
    {
        return Cache::rememberForever(self::KEY, $builder);
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
