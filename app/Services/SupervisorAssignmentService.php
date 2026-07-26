<?php

namespace App\Services;

use App\Models\CollectorAssignment;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;

class SupervisorAssignmentService
{
    /**
     * The single authoritative resolver for which clusters a supervisor may see/act on.
     * root/admin_estate are exempt (see everything) - mirrors how those roles already
     * behave elsewhere in this app. Always live-queried, never a cached snapshot.
     */
    public function clusterIdsFor(User $supervisor): array
    {
        if ($supervisor->hasAnyRole(['root', 'super_admin', 'admin_estate'])) {
            return Unit::query()->select('cluster_id')->distinct()->pluck('cluster_id')->all();
        }

        return SupervisorAssignment::query()
            ->forSupervisor($supervisor->id)
            ->active()
            ->currentlyEffective()
            ->pluck('cluster_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Collectors whose OWN active assignments touch any cluster this supervisor oversees.
     * Always derived from clusterIdsFor(), never resolved independently.
     */
    public function collectorIdsFor(User $supervisor): array
    {
        $clusterIds = $this->clusterIdsFor($supervisor);
        if (! $clusterIds) {
            return [];
        }

        if ($supervisor->hasAnyRole(['root', 'super_admin', 'admin_estate'])) {
            return CollectorAssignment::query()->active()->currentlyEffective()
                ->pluck('collector_id')->unique()->values()->all();
        }

        $unitIdsInScope = Unit::query()->whereIn('cluster_id', $clusterIds)->pluck('id')->all();

        return CollectorAssignment::query()
            ->active()
            ->currentlyEffective()
            ->where(function ($q) use ($clusterIds, $unitIdsInScope) {
                $q->whereIn('cluster_id', $clusterIds)
                    ->orWhereIn('unit_id', $unitIdsInScope);
            })
            ->pluck('collector_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Derived strictly from clusterIdsFor(), never independently resolved, to prevent scope drift.
     */
    public function unitIdsFor(User $supervisor): array
    {
        $clusterIds = $this->clusterIdsFor($supervisor);
        if (! $clusterIds) {
            return [];
        }

        return Unit::query()->whereIn('cluster_id', $clusterIds)->pluck('id')->all();
    }

    public function residentIdsFor(User $supervisor): array
    {
        $unitIds = $this->unitIdsFor($supervisor);
        if (! $unitIds) {
            return [];
        }

        return Unit::query()->whereIn('id', $unitIds)->pluck('resident_id')->filter()->unique()->values()->all();
    }

    public function isClusterAssigned(User $supervisor, string $clusterId): bool
    {
        return in_array($clusterId, $this->clusterIdsFor($supervisor), true);
    }

    public function isCollectorAssigned(User $supervisor, int $collectorId): bool
    {
        return in_array($collectorId, $this->collectorIdsFor($supervisor), true);
    }

    public function assertCollectorAssigned(User $supervisor, int $collectorId): void
    {
        abort_unless($this->isCollectorAssigned($supervisor, $collectorId), 403, 'Kolektor ini bukan tanggung jawab Anda.');
    }
}
