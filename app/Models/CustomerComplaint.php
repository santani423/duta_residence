<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerComplaint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'user_id', 'title', 'category', 'priority', 'description',
        'status', 'attachment_path', 'closed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(CustomerComplaintComment::class);
    }
}
