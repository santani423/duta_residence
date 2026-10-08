<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentPromise extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_BROKEN = 'broken';

    public const STATUS_RESCHEDULED = 'rescheduled';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'unit_id', 'billing_id', 'promised_amount', 'promised_date', 'payment_method',
        'reason', 'follow_up_date', 'status', 'notes', 'created_by',
        'collector_id', 'visit_id', 'fulfilled_amount', 'fulfilled_at', 'broken_at',
        'cancelled_at', 'cancel_reason', 'client_uuid', 'version', 'updated_by',
    ];

    protected $casts = [
        'promised_amount' => 'decimal:2',
        'promised_date' => 'date',
        'follow_up_date' => 'date',
        'fulfilled_amount' => 'decimal:2',
        'fulfilled_at' => 'datetime',
        'broken_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'version' => 'integer',
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

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function visit()
    {
        return $this->belongsTo(CollectorVisit::class, 'visit_id');
    }
}
