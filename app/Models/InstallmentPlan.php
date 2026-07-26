<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentPlan extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'unit_id', 'billing_id', 'total_outstanding', 'number_of_installments',
        'installment_amount', 'frequency', 'start_date', 'status', 'reason', 'created_by',
    ];

    protected $casts = [
        'total_outstanding' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'start_date' => 'date',
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

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function approvalRequest()
    {
        return $this->morphOne(ApprovalRequest::class, 'requestable');
    }
}
