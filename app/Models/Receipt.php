<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasStringPrimaryKey;

    protected $primaryKey = 'number';

    protected $fillable = [
        'number', 'payment_transaction_id', 'unit_id', 'transaction_date', 'resident_name', 'cluster_name',
        'block', 'lot_number', 'total_billing', 'total_penalty', 'grand_total', 'deposit_amount', 'balance_used',
        'billing_count', 'billing_periods', 'loket_code', 'cashier_name',
        'payment_method_id', 'payment_channel_id', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'total_billing' => 'decimal:2',
        'total_penalty' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_used' => 'decimal:2',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function billings()
    {
        return $this->hasMany(Billing::class, 'receipt_number', 'number');
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
