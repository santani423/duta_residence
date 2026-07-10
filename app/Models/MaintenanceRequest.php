<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'user_id', 'unit_label', 'category', 'description',
        'urgency', 'preferred_schedule', 'status', 'technician_name',
        'technician_schedule', 'technician_notes', 'rating', 'rating_notes',
        'attachment_path', 'completed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'preferred_schedule' => 'datetime',
        'technician_schedule' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
