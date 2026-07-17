<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClusterMap extends Model
{
    use HasFactory;

    protected $fillable = [
        'cluster_id', 'canvas_type', 'background_image_path',
        'background_x', 'background_y', 'background_width', 'background_height',
        'background_scale', 'background_opacity', 'canvas_width', 'canvas_height',
        'grid_enabled', 'grid_size', 'grid_visible', 'snap_enabled', 'scale_meters_per_pixel',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'background_x' => 'float',
        'background_y' => 'float',
        'background_width' => 'float',
        'background_height' => 'float',
        'background_scale' => 'float',
        'background_opacity' => 'float',
        'grid_enabled' => 'boolean',
        'grid_visible' => 'boolean',
        'snap_enabled' => 'boolean',
        'scale_meters_per_pixel' => 'float',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function objects()
    {
        return $this->hasMany(ClusterMapObject::class)->orderBy('layer_order');
    }

    public function versions()
    {
        return $this->hasMany(ClusterMapVersion::class)->orderByDesc('created_at');
    }
}
