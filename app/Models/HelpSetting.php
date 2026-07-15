<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class HelpSetting extends Model
{
    protected $fillable = ['scope_type', 'scope_key', 'is_enabled', 'updated_by'];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public const CACHE_KEY = 'help_settings.all';

    public static function allCached()
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->get());
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Urutan resolusi dari paling spesifik ke paling umum: component > page > module > role > global.
     * Override yang lebih spesifik selalu menang (baik menyalakan maupun mematikan), baris global
     * (scope_key null) hanya dipakai sebagai nilai default kalau tidak ada override sama sekali.
     */
    public static function isEnabled(array $scope): bool
    {
        $settings = static::allCached();

        foreach (['component', 'page', 'module', 'role'] as $type) {
            $key = $scope[$type] ?? null;
            if (! $key) {
                continue;
            }
            $match = $settings->first(fn ($setting) => $setting->scope_type === $type && $setting->scope_key === $key);
            if ($match) {
                return $match->is_enabled;
            }
        }

        $global = $settings->firstWhere('scope_type', 'global');

        return $global ? $global->is_enabled : true;
    }
}
