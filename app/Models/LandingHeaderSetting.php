<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingHeaderSetting extends Model
{
    protected $fillable = [
        'logo_media_id', 'site_name', 'sticky_enabled', 'show_login_button',
        'login_button_text', 'cta_button_text', 'cta_button_url', 'cta_button_enabled',
    ];

    protected $casts = [
        'sticky_enabled' => 'boolean',
        'show_login_button' => 'boolean',
        'cta_button_enabled' => 'boolean',
    ];

    public function logo()
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'site_name' => 'Duta Indah Residences',
            'sticky_enabled' => true,
            'show_login_button' => true,
            'login_button_text' => 'Login',
            'cta_button_enabled' => false,
        ]);
    }
}
