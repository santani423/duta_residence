<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnitDeposit extends Model
{
    public const TYPE_PAYMENT_OVERPAYMENT = 'overpayment';

    public const TYPE_REFUND_CREDIT = 'refund_credit';

    public const TYPE_MANUAL_CREDIT = 'manual_credit';

    public const TYPE_BALANCE_USAGE = 'balance_usage';

    public const TYPE_MANUAL_DEBIT = 'manual_debit';

    public const TYPE_REFUND_DEBIT = 'refund_debit';

    public const TYPE_REVERSAL = 'reversal';

    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    protected $fillable = [
        'unit_id', 'type', 'direction', 'amount', 'balance_before', 'balance_after', 'payment_transaction_id',
        'receipt_number', 'reference_type', 'reference_id', 'reversal_of_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function paymentTransaction()
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function isCredit(): bool
    {
        return $this->direction === self::DIRECTION_CREDIT;
    }
}
