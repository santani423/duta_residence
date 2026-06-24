<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'user_id', 'type', 'channel', 'recipient', 'message',
        'read_status', 'status', 'attempts', 'sent_at', 'failed_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
