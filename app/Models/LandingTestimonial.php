<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingTestimonial extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['name', 'photo_media_id', 'position', 'content', 'rating', 'order', 'is_active'];

    protected $casts = [
        'rating' => 'integer',
        'is_active' => 'boolean',
    ];

    public function photo()
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
    }
}
