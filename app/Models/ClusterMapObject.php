<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClusterMapObject extends Model
{
    use HasFactory, HasUuids;

    public const CATEGORY_UNIT = 'unit';
    public const CATEGORY_COMPONENT = 'component';
    public const CATEGORY_LABEL = 'label';

    protected $fillable = [
        'id', 'cluster_map_id', 'object_category', 'unit_id', 'component_type_id', 'shape_type',
        'x', 'y', 'width', 'height', 'rotation', 'points',
        'fill_color', 'stroke_color', 'stroke_width', 'opacity',
        'layer_order', 'is_locked', 'is_color_auto', 'label_text', 'group_id', 'metadata',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'width' => 'float',
        'height' => 'float',
        'rotation' => 'float',
        'points' => 'array',
        'stroke_width' => 'float',
        'opacity' => 'float',
        'layer_order' => 'integer',
        'is_locked' => 'boolean',
        'is_color_auto' => 'boolean',
        'metadata' => 'array',
    ];

    public function clusterMap()
    {
        return $this->belongsTo(ClusterMap::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function componentType()
    {
        return $this->belongsTo(ClusterMapComponentType::class, 'component_type_id');
    }
}
