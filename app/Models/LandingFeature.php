<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingFeature extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = [
        'icon', 'image_media_id', 'title', 'description', 'badge', 'cta_text', 'cta_url', 'order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function image()
    {
        return $this->belongsTo(MediaAsset::class, 'image_media_id');
    }
}
