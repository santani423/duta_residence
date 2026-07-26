<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadcastRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'broadcast_id', 'recipient_type', 'recipient_id', 'name', 'phone',
        'delivery_status', 'provider_response', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class);
    }
}
