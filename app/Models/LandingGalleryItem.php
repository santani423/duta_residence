<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingGalleryItem extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['album_id', 'type', 'media_id', 'video_url', 'caption', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function album()
    {
        return $this->belongsTo(LandingGalleryAlbum::class, 'album_id');
    }

    public function media()
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }
}
