<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClusterMapComponentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'category', 'description', 'icon',
        'fill_color', 'stroke_color', 'default_shape_type', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
