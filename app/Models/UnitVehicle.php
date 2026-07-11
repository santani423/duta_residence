<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitVehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'vehicle_type', 'brand', 'model', 'color', 'plate_number',
        'sticker_number', 'sticker_valid_until', 'owner_name', 'status', 'created_by',
    ];

    protected $casts = [
        'sticker_valid_until' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
