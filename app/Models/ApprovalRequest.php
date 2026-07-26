<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    public const TYPE_REVERSAL = 'reversal';

    public const TYPE_PENALTY_WAIVER = 'penalty_waiver';

    public const TYPE_INSTALLMENT_PLAN = 'installment_plan';

    public const TYPE_BILLING_ADJUSTMENT = 'billing_adjustment';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'request_number', 'type', 'requestable_type', 'requestable_id', 'requested_by',
        'related_collector_id', 'related_unit_id', 'related_resident_id',
        'before_value', 'after_value', 'reason', 'status', 'supervisor_notes', 'amount',
        'submitted_at', 'decided_by', 'decided_at', 'expires_at',
    ];

    protected $casts = [
        'before_value' => 'array',
        'after_value' => 'array',
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function requestable()
    {
        return $this->morphTo();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function relatedCollector()
    {
        return $this->belongsTo(User::class, 'related_collector_id');
    }

    public function relatedUnit()
    {
        return $this->belongsTo(Unit::class, 'related_unit_id');
    }

    public function relatedResident()
    {
        return $this->belongsTo(Resident::class, 'related_resident_id');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function documents()
    {
        return $this->morphMany(ManagedFile::class, 'entity');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW]);
    }
}
