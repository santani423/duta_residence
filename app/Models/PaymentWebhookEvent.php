<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    protected $fillable = ['provider', 'event_id', 'provider_reference', 'status', 'payload', 'error_message'];

    protected $casts = [
        'payload' => 'array',
    ];
}
