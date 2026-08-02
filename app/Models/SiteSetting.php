<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_name', 'logo_media_id', 'default_theme', 'primary_color', 'secondary_color',
        'default_language', 'maintenance_mode', 'maintenance_message',
        'analytics_script', 'pixel_script', 'custom_css', 'custom_js',
    ];

    protected $casts = [
        'maintenance_mode' => 'boolean',
    ];

    public function logo()
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_name' => 'Duta Indah Residences',
            'default_theme' => 'system',
            'primary_color' => '#0f766e',
            'secondary_color' => '#f59e0b',
            'default_language' => 'id',
            'maintenance_mode' => false,
        ]);
    }
}
