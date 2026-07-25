<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingGalleryAlbum extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['title', 'slug', 'cover_media_id', 'category_id', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cover()
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function category()
    {
        return $this->belongsTo(LandingContentCategory::class, 'category_id');
    }

    public function items()
    {
        return $this->hasMany(LandingGalleryItem::class, 'album_id')->orderBy('order');
    }
}
