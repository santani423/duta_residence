<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CollectorAssignment extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'collector_id', 'scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id',
        'is_active', 'assigned_by', 'start_date', 'end_date', 'status', 'priority', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCollector($query, int $collectorId)
    {
        return $query->where('collector_id', $collectorId);
    }

    /**
     * A currently-scheduled assignment: today falls within [start_date, end_date]
     * (either bound may be null/open-ended). This is the single definition of
     * "in effect right now" used both for scoping (CollectorAssignmentService)
     * and for admin "active assignments" listings, so the two can never disagree.
     *
     * Uses whereDate() (not a raw string where()) because a `date`-cast column is
     * persisted with a full "Y-m-d H:i:s" value (Eloquent always writes the
     * connection's datetime format for date casts) - MySQL's native DATE column
     * type silently truncates that to just the date, but SQLite (this app's test
     * driver) stores it verbatim as TEXT, so a plain string "<=" comparison
     * against a bare "Y-m-d" today-string would lexicographically fail there.
     * whereDate() normalizes both sides via SQL DATE()/date(), which is correct
     * under every driver.
     */
    public function scopeCurrentlyEffective(Builder $query)
    {
        $today = Carbon::today()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }
}
