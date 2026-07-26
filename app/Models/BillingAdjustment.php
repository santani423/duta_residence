<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingAdjustment extends Model
{
    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_PENALTY = 'penalty';

    public const TYPE_PRINCIPAL_CORRECTION = 'principal_correction';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'billing_id', 'adjustment_type', 'original_value', 'new_value', 'reason', 'status', 'created_by',
    ];

    protected $casts = [
        'original_value' => 'decimal:2',
        'new_value' => 'decimal:2',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'requestable');
    }
}
