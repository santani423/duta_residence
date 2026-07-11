<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentPromise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'billing_id', 'promised_amount', 'promised_date', 'payment_method',
        'reason', 'follow_up_date', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'promised_amount' => 'decimal:2',
        'promised_date' => 'date',
        'follow_up_date' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
