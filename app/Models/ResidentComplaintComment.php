<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentComplaintComment extends Model
{
    protected $fillable = [
        'resident_complaint_id', 'user_id', 'body', 'attachment_path', 'is_staff_response',
    ];

    protected $casts = [
        'is_staff_response' => 'boolean',
    ];

    public function complaint()
    {
        return $this->belongsTo(ResidentComplaint::class, 'resident_complaint_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
