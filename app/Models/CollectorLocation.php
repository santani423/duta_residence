<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectorLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'collector_id', 'latitude', 'longitude', 'accuracy_meters', 'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy_meters' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }
}
