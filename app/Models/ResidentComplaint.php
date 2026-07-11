<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResidentComplaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'user_id', 'title', 'category', 'priority', 'description',
        'status', 'attachment_path', 'closed_at', 'assigned_to', 'division',
        'target_date', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'target_date' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments()
    {
        return $this->hasMany(ResidentComplaintComment::class);
    }
}
