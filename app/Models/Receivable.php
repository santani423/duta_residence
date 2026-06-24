<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    protected $fillable = ['customer_id', 'billing_id', 'year', 'month', 'amount', 'snapshot_date', 'is_settled', 'settled_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'snapshot_date' => 'date',
        'is_settled' => 'boolean',
        'settled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }
}
