<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    protected $fillable = [
        'billing_id', 'payment_transaction_id', 'principal_amount', 'penalty_amount',
        'total_amount', 'overdue_months', 'penalty_rule_id', 'penalty_rule_snapshot', 'calculated_at',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'overdue_months' => 'integer',
        'penalty_rule_snapshot' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function penaltyRule()
    {
        return $this->belongsTo(PenaltyRule::class);
    }
}
