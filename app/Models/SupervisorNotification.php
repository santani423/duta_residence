<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorNotification extends Model
{
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const HANDLED_OPEN = 'open';

    public const HANDLED_IN_PROGRESS = 'in_progress';

    public const HANDLED_HANDLED = 'handled';

    public const HANDLED_ESCALATED = 'escalated';

    public const CATEGORIES = [
        'ptp_due', 'broken_promise', 'collector_inactive', 'collector_not_sending_location',
        'target_not_met', 'payment_awaiting_verification', 'approval_request', 'complaint_high_priority',
        'emergency_active', 'visit_failed', 'tunggakan_large', 'resident_violation',
        'assignment_change', 'payment_sync_error',
    ];

    protected $fillable = [
        'category', 'priority', 'title', 'description', 'reference_type', 'reference_id', 'related_collector_id',
        'read_status', 'handled_status', 'handling_deadline', 'responsible_user_id', 'escalation_log',
    ];

    protected $casts = [
        'handling_deadline' => 'datetime',
        'escalation_log' => 'array',
    ];

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function relatedCollector()
    {
        return $this->belongsTo(User::class, 'related_collector_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('read_status', 'unread');
    }

    public function scopeUnhandled($query)
    {
        return $query->whereNotIn('handled_status', [self::HANDLED_HANDLED]);
    }
}
