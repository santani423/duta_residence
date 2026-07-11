<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOccupant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'type', 'name', 'relationship', 'phone', 'identity_number',
        'photo_path', 'start_date', 'access_valid_until', 'emergency_contact',
        'access_status', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'access_valid_until' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function scopeFamily(Builder $query): Builder
    {
        return $query->where('type', 'family');
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->where('type', 'staff');
    }
}
