<?php

namespace App\Services;

use App\Models\CollectionActivity;
use Illuminate\Contracts\Pagination\CursorPaginator;

/** Timeline penagihan satu unit, terbaru lebih dulu, dengan cursor pagination. */
class CollectionTimelineService
{
    public function forUnit(string $unitId, array $types = [], int $perPage = 20): CursorPaginator
    {
        return CollectionActivity::query()
            ->where('unit_id', $unitId)
            ->when($types !== [], fn ($q) => $q->whereIn('type', $types))
            ->with(['creator:id,name', 'collector:id,name'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->cursorPaginate(min(max($perPage, 1), 100));
    }
}
