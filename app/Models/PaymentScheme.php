<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentScheme extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'unit_id', 'status', 'reason', 'discount_type', 'original_principal', 'principal_discount',
        'original_penalty', 'penalty_reduction', 'final_amount', 'submitted_by', 'submitted_at',
        'decided_by', 'decided_at', 'review_notes', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'original_principal' => 'decimal:2',
        'principal_discount' => 'decimal:2',
        'original_penalty' => 'decimal:2',
        'penalty_reduction' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function items()
    {
        return $this->hasMany(PaymentSchemeItem::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'requestable');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
