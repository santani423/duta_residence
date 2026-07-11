<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectorVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'collector_id', 'visit_date', 'purpose', 'result', 'met_with',
        'notes', 'checkin_latitude', 'checkin_longitude', 'status', 'next_visit_date',
        'created_by',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
        'next_visit_date' => 'date',
        'checkin_latitude' => 'decimal:7',
        'checkin_longitude' => 'decimal:7',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
