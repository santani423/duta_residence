<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClusterRateSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cluster_id', 'rate', 'effective_date', 'notes', 'is_active',
        'activated_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
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

    public function isEffective(): bool
    {
        return $this->is_active && $this->effective_date->lte(today());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffective(Builder $query): Builder
    {
        return $query->active()->whereDate('effective_date', '<=', today());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->whereDate('effective_date', '>', today());
    }
}
