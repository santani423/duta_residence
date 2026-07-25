<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingSeoSetting extends Model
{
    protected $fillable = [
        'meta_title', 'meta_description', 'meta_keywords', 'og_title', 'og_description',
        'og_image_media_id', 'twitter_card_type', 'favicon_media_id', 'structured_data',
    ];

    public function ogImage()
    {
        return $this->belongsTo(MediaAsset::class, 'og_image_media_id');
    }

    public function favicon()
    {
        return $this->belongsTo(MediaAsset::class, 'favicon_media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'meta_title' => 'Grand Duta Estate Management',
            'meta_description' => 'Kelola kawasan Anda lebih modern, aman, dan terintegrasi.',
            'twitter_card_type' => 'summary_large_image',
        ]);
    }
}
