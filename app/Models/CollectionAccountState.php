<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cache turunan status penagihan satu unit. Hanya ditulis oleh CollectionAccountService::refresh();
 * jangan pernah diubah langsung karena akan ditimpa pada refresh berikutnya.
 */
class CollectionAccountState extends Model
{
    public const STATUS_CURRENT = 'current';

    public const STATUS_DUE_SOON = 'due_soon';

    public const STATUS_DUE_TODAY = 'due_today';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PROMISE_TO_PAY = 'promise_to_pay';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_CURRENT, self::STATUS_DUE_SOON, self::STATUS_DUE_TODAY, self::STATUS_OVERDUE,
        self::STATUS_PARTIALLY_PAID, self::STATUS_PROMISE_TO_PAY, self::STATUS_DISPUTED,
        self::STATUS_ESCALATED, self::STATUS_PAID,
    ];

    public const PRIORITY_CRITICAL = 'critical';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_LEVELS = [
        self::PRIORITY_CRITICAL, self::PRIORITY_HIGH, self::PRIORITY_MEDIUM, self::PRIORITY_NORMAL,
    ];

    protected $primaryKey = 'unit_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'unit_id', 'customer_resident_id', 'collector_id', 'outstanding_principal',
        'outstanding_penalty', 'outstanding_total', 'open_invoice_count', 'oldest_due_date',
        'next_due_date', 'aging_days', 'aging_bucket', 'status', 'priority_score', 'priority_level',
        'last_contact_at', 'last_contact_result', 'next_follow_up_at', 'failed_contact_count',
        'failed_visit_count', 'broken_ptp_count', 'active_promise_id', 'refreshed_at',
    ];

    protected $casts = [
        'outstanding_principal' => 'decimal:2',
        'outstanding_penalty' => 'decimal:2',
        'outstanding_total' => 'decimal:2',
        'open_invoice_count' => 'integer',
        'oldest_due_date' => 'date',
        'next_due_date' => 'date',
        'aging_days' => 'integer',
        'priority_score' => 'integer',
        'last_contact_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'failed_contact_count' => 'integer',
        'failed_visit_count' => 'integer',
        'broken_ptp_count' => 'integer',
        'refreshed_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function customer()
    {
        return $this->belongsTo(Resident::class, 'customer_resident_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function activePromise()
    {
        return $this->belongsTo(PaymentPromise::class, 'active_promise_id');
    }
}
