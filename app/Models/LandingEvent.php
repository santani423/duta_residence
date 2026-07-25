<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingEvent extends Model
{
    use HasFactory, HasOrder, Publishable, SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'banner_media_id', 'description', 'location',
        'starts_at', 'ends_at', 'registration_url', 'status', 'order', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function banner()
    {
        return $this->belongsTo(MediaAsset::class, 'banner_media_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
