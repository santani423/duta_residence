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
        'reassigned_from_id', 'reassign_reason',
    ];

    protected $casts = [
        'collector_id' => 'integer',
        'is_active' => 'boolean',
        // Diserialisasi sebagai Y-m-d: cast 'date' biasa menghasilkan datetime UTC
        // (mis. 2026-10-07T17:00:00Z untuk 8 Okt WIB) sehingga tanggal di web mundur sehari.
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
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

    /** Assignment lama yang dipindahkan (reassign) menjadi assignment ini. */
    public function reassignedFrom()
    {
        return $this->belongsTo(self::class, 'reassigned_from_id');
    }

    /** Assignment pengganti yang dibuat saat assignment ini di-reassign. */
    public function reassignedTo()
    {
        return $this->hasOne(self::class, 'reassigned_from_id');
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
     * Uses whereDate() (not a raw string where()) because stored values are not
     * uniform: with the `date:Y-m-d` cast a string input is written as "Y-m-d"
     * while a Carbon input is written in the connection's "Y-m-d H:i:s" format.
     * MySQL's native DATE column type silently truncates both to just the date,
     * but SQLite (this app's test driver) stores them verbatim as TEXT, so a plain
     * string "<=" comparison against a bare "Y-m-d" today-string would
     * lexicographically fail there. whereDate() normalizes both sides via SQL
     * DATE()/date(), which is correct under every driver.
     */
    public function scopeCurrentlyEffective(Builder $query)
    {
        $today = Carbon::today()->toDateString();

        return $query
            ->where(fn (Builder $q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }
}
