<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingContactSetting extends Model
{
    protected $fillable = [
        'address', 'phone', 'whatsapp', 'email', 'maps_embed_url', 'maps_lat', 'maps_lng', 'business_hours',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'maps_lat' => 'float',
        'maps_lng' => 'float',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'business_hours' => [['label' => 'Senin - Sabtu', 'value' => '08.00 - 17.00 WIB']],
        ]);
    }
}
