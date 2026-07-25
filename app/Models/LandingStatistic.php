<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingStatistic extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['label', 'value', 'suffix', 'icon', 'source', 'order', 'is_active'];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * When `source` is bound to a live count instead of "manual", resolve the
     * current DB count so the public landing page always shows a real number.
     */
    public function resolvedValue(): float
    {
        return match ($this->source) {
            'residents_count' => (float) Resident::query()->count(),
            'units_count' => (float) Unit::query()->count(),
            'clusters_count' => (float) Cluster::query()->count(),
            default => (float) $this->value,
        };
    }
}
