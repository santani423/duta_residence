<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Publishable
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
