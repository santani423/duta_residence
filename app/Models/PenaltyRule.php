<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenaltyRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'cluster_id', 'minimum_overdue_month', 'maximum_overdue_month',
        'penalty_amount', 'is_active', 'effective_start_date', 'effective_end_date',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'minimum_overdue_month' => 'integer',
        'maximum_overdue_month' => 'integer',
        'penalty_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_start_date' => 'date',
        'effective_end_date' => 'date',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function covers(int $overdueMonths): bool
    {
        if ($overdueMonths < $this->minimum_overdue_month) {
            return false;
        }

        return $this->maximum_overdue_month === null || $overdueMonths <= $this->maximum_overdue_month;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, $date): Builder
    {
        return $query->active()
            ->whereDate('effective_start_date', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('effective_end_date')->orWhereDate('effective_end_date', '>=', $date));
    }
}
