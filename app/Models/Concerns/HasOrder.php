<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait HasOrder
{
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    /**
     * Swap this record's `order` with the adjacent active sibling in the given
     * direction, powering the admin "move up / move down" reorder buttons.
     */
    public function moveOrder(string $direction): void
    {
        $sibling = static::query()
            ->where('id', '!=', $this->id)
            ->when($direction === 'up', fn ($q) => $q->where('order', '<=', $this->order)->orderByDesc('order'))
            ->when($direction === 'down', fn ($q) => $q->where('order', '>=', $this->order)->orderBy('order'))
            ->first();

        if (! $sibling) {
            return;
        }

        DB::transaction(function () use ($sibling) {
            $currentOrder = $this->order;
            $this->update(['order' => $sibling->order]);
            $sibling->update(['order' => $currentOrder]);
        });
    }
}
