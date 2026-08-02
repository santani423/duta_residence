<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingFooterSetting extends Model
{
    protected $fillable = [
        'logo_media_id', 'description', 'copyright_text', 'show_social_links', 'show_quick_links',
    ];

    protected $casts = [
        'show_social_links' => 'boolean',
        'show_quick_links' => 'boolean',
    ];

    public function logo()
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'description' => 'Sistem pengelolaan kawasan modern untuk perumahan, apartemen, dan cluster.',
            'copyright_text' => 'Duta Indah Residences. Seluruh hak cipta dilindungi.',
            'show_social_links' => true,
            'show_quick_links' => true,
        ]);
    }
}
