<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatermarkSetting extends Model
{
    protected $fillable = [
        'enabled', 'type', 'text_content', 'media_id', 'opacity',
        'mode', 'size', 'position', 'spacing', 'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'opacity' => 'integer',
        'size' => 'integer',
        'spacing' => 'integer',
    ];

    public function media()
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'enabled' => false,
            'type' => 'text',
            'text_content' => 'Duta Indah Residences',
            'opacity' => 30,
            'mode' => 'single',
            'size' => 200,
            'position' => 'bottom-right',
            'spacing' => 150,
        ]);
    }
}
