<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentScheme extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'unit_id', 'status', 'reason', 'discount_type', 'original_principal', 'principal_discount',
        'original_penalty', 'penalty_reduction', 'final_amount', 'requested_snapshot', 'adjusted_by', 'adjusted_at', 'submitted_by', 'submitted_at',
        'decided_by', 'decided_at', 'review_notes', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'original_principal' => 'decimal:2',
        'principal_discount' => 'decimal:2',
        'original_penalty' => 'decimal:2',
        'penalty_reduction' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'requested_snapshot' => 'array',
        'adjusted_at' => 'datetime',
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

    /** Months that are actually part of the scheme (Admin may have rejected some of the requested ones). */
    public function includedItems()
    {
        return $this->items()->where('status', PaymentSchemeItem::STATUS_INCLUDED);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'requestable');
    }

    /** Filters shared by the list endpoint and its PDF export, so both always show the same rows. */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['unit_id'] ?? null, fn ($q, $value) => $q->where('unit_id', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('unit_id', 'like', "%{$value}%")
                ->orWhereHas('unit.resident', fn ($r) => $r->where('name', 'like', "%{$value}%"))));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
