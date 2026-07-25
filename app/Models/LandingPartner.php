<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingPartner extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['name', 'logo_media_id', 'website_url', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function logo()
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }
}
