<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClusterMapVersion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['cluster_map_id', 'label', 'snapshot', 'created_by', 'created_at'];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function clusterMap()
    {
        return $this->belongsTo(ClusterMap::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
