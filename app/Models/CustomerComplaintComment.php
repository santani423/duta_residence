<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerComplaintComment extends Model
{
    protected $fillable = [
        'customer_complaint_id', 'user_id', 'body', 'attachment_path', 'is_staff_response',
    ];

    protected $casts = [
        'is_staff_response' => 'boolean',
    ];

    public function complaint()
    {
        return $this->belongsTo(CustomerComplaint::class, 'customer_complaint_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
