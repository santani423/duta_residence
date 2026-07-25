<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingArticle extends Model
{
    use HasFactory, HasOrder, Publishable, SoftDeletes;

    protected $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image_media_id', 'thumbnail_media_id',
        'meta_title', 'meta_description', 'meta_keywords',
        'status', 'published_at', 'author_id', 'order', 'is_active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(LandingContentCategory::class, 'category_id');
    }

    public function featuredImage()
    {
        return $this->belongsTo(MediaAsset::class, 'featured_image_media_id');
    }

    public function thumbnail()
    {
        return $this->belongsTo(MediaAsset::class, 'thumbnail_media_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
