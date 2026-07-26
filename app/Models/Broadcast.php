<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    public const TYPE_COLLECTOR = 'collector';

    public const TYPE_RESIDENT = 'resident';

    public const TYPE_ANNOUNCEMENT = 'announcement';

    protected $fillable = [
        'type', 'message', 'target_criteria', 'sender_id', 'recipient_count', 'success_count', 'fail_count',
    ];

    protected $casts = [
        'target_criteria' => 'array',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients()
    {
        return $this->hasMany(BroadcastRecipient::class);
    }
}
