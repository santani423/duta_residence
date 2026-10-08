<?php

namespace App\Services;

use App\Models\CollectorAssignment;
use App\Models\Unit;
use App\Models\User;

class CollectorAssignmentService
{
    /**
     * The single authoritative resolver for which units a collector may see/act on.
     * Always live-queried (never a cached snapshot) so a unit's resident reassignment,
     * a new assignment grant, or a revoked one is reflected immediately.
     */
    public function unitIdsFor(User $collector): array
    {
        $assignments = CollectorAssignment::query()
            ->forCollector($collector->id)
            ->active()
            ->currentlyEffective()
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $unitIds = collect();

        $clusterIds = $assignments->where('scope_type', 'cluster')->pluck('cluster_id')->filter()->all();
        if ($clusterIds) {
            $unitIds = $unitIds->merge(Unit::query()->whereIn('cluster_id', $clusterIds)->pluck('id'));
        }

        foreach ($assignments->where('scope_type', 'block') as $assignment) {
            $unitIds = $unitIds->merge(
                Unit::query()->where('cluster_id', $assignment->cluster_id)->where('block', $assignment->block)->pluck('id')
            );
        }

        $directUnitIds = $assignments->where('scope_type', 'unit')->pluck('unit_id')->filter()->all();
        if ($directUnitIds) {
            $unitIds = $unitIds->merge($directUnitIds);
        }

        $residentIds = $assignments->where('scope_type', 'resident')->pluck('resident_id')->filter()->all();
        if ($residentIds) {
            $unitIds = $unitIds->merge(Unit::query()->whereIn('resident_id', $residentIds)->pluck('id'));
        }

        return $unitIds->unique()->values()->all();
    }

    /**
     * Always derived from unitIdsFor(), never resolved independently, so unit-vs-resident
     * scope can never drift out of sync with each other.
     */
    public function residentIdsFor(User $collector): array
    {
        $unitIds = $this->unitIdsFor($collector);
        if (! $unitIds) {
            return [];
        }

        return Unit::query()->whereIn('id', $unitIds)->pluck('resident_id')->unique()->values()->all();
    }

    public function isUnitAssigned(User $collector, string $unitId): bool
    {
        return in_array($unitId, $this->unitIdsFor($collector), true);
    }

    public function assertUnitAssigned(User $collector, string $unitId): void
    {
        abort_unless($this->isUnitAssigned($collector, $unitId), 403, 'Unit ini tidak ditugaskan kepada Anda.');
    }

    /**
     * Kebalikan dari unitIdsFor(): collector utama untuk sekumpulan unit sekaligus (cache
     * collection account, monitoring supervisor). Memakai definisi assignment "aktif & berlaku"
     * yang sama dengan unitIdsFor() supaya keduanya tidak pernah berbeda. Bila beberapa
     * assignment mencakup unit yang sama, scope paling spesifik menang (unit > resident >
     * block > cluster), lalu assignment terbaru.
     *
     * @param  iterable<Unit>  $units
     * @return array<string, int> unit_id => collector_id
     */
    public function primaryCollectorIdsFor(iterable $units): array
    {
        $assignments = CollectorAssignment::query()
            ->active()
            ->currentlyEffective()
            ->orderByDesc('id')
            ->get(['id', 'collector_id', 'scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id']);

        if ($assignments->isEmpty()) {
            return [];
        }

        $byUnit = $assignments->where('scope_type', 'unit')->groupBy('unit_id');
        $byResident = $assignments->where('scope_type', 'resident')->groupBy('resident_id');
        $byBlock = $assignments->where('scope_type', 'block')->groupBy(fn ($a) => $a->cluster_id.'|'.$a->block);
        $byCluster = $assignments->where('scope_type', 'cluster')->groupBy('cluster_id');

        $map = [];
        foreach ($units as $unit) {
            $match = $byUnit->get($unit->id)?->first()
                ?? ($unit->resident_id ? $byResident->get($unit->resident_id)?->first() : null)
                ?? $byBlock->get($unit->cluster_id.'|'.$unit->block)?->first()
                ?? $byCluster->get($unit->cluster_id)?->first();

            if ($match) {
                $map[$unit->id] = (int) $match->collector_id;
            }
        }

        return $map;
    }
}
