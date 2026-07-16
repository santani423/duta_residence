<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Billing extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_UNPAID = '01';

    public const STATUS_PAID = '02';

    public const STATUS_PARTIAL = '03';

    public const STATUS_CANCELLED = '04';

    protected $fillable = [
        'unit_id', 'year', 'month', 'amount', 'principal_paid', 'penalty', 'penalty_paid',
        'penalty_waived_amount', 'penalty_notified_tier', 'discount', 'discount_rule_id',
        'discount_set_by', 'discount_set_at', 'discount_reason', 'status_id',
        'is_penalty_eligible', 'is_discount_eligible', 'billing_type', 'approved_by',
        'approved_at', 'approval_notes', 'paid_at', 'receipt_number', 'loket_code',
        'processed_by', 'spt_print_count', 'created_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'principal_paid' => 'decimal:2',
        'penalty' => 'decimal:2',
        'penalty_paid' => 'decimal:2',
        'penalty_waived_amount' => 'decimal:2',
        'penalty_notified_tier' => 'integer',
        'discount' => 'decimal:2',
        'discount_set_at' => 'datetime',
        'is_penalty_eligible' => 'boolean',
        'is_discount_eligible' => 'boolean',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function status()
    {
        return $this->belongsTo(BillingStatus::class, 'status_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function discountRule()
    {
        return $this->belongsTo(DiscountRule::class);
    }

    public function discountSetter()
    {
        return $this->belongsTo(User::class, 'discount_set_by');
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_number', 'number');
    }

    public function paymentTransactions()
    {
        return $this->belongsToMany(PaymentTransaction::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function penaltyWaivers()
    {
        return $this->hasMany(PenaltyWaiver::class);
    }

    public function isPaid(): bool
    {
        return $this->status_id === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status_id === self::STATUS_CANCELLED;
    }

    public function isOutstanding(): bool
    {
        return in_array($this->status_id, [self::STATUS_UNPAID, self::STATUS_PARTIAL], true);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status_id', self::STATUS_UNPAID);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status_id', [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }
}
