<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = [
        'title', 'subtitle', 'description', 'background_media_id', 'video_url',
        'cta_text', 'cta_url', 'cta_target', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function backgroundMedia()
    {
        return $this->belongsTo(MediaAsset::class, 'background_media_id');
    }
}
