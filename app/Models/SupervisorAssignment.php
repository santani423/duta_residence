<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SupervisorAssignment extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'supervisor_id', 'cluster_id', 'is_active', 'start_date', 'end_date',
        'status', 'notes', 'assigned_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSupervisor($query, int $supervisorId)
    {
        return $query->where('supervisor_id', $supervisorId);
    }

    /**
     * Same "in effect right now" definition as CollectorAssignment::scopeCurrentlyEffective() -
     * whereDate() (not a raw string where()) so SQLite's verbatim-TEXT date storage and MySQL's
     * truncating DATE column both compare correctly. Kept as one definition per model rather than
     * a shared trait since the two assignment tables have historically been allowed to diverge.
     */
    public function scopeCurrentlyEffective(Builder $query)
    {
        $today = Carbon::today()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }
}
