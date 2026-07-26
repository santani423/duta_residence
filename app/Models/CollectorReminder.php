<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectorReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id', 'resident_id', 'billing_id', 'message', 'phone', 'sent_at', 'sent_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
