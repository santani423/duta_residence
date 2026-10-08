<?php

namespace App\Services;

use App\Models\CollectorAssignment;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class SupervisorAssignmentService
{
    /**
     * Role yang melihat seluruh estate tanpa assignment supervisor. Sumber tunggal ada di
     * CollectionScopeService; konstanta ini dipertahankan agar pemanggil lama tetap jalan.
     */
    public const FULL_SCOPE_ROLES = CollectionScopeService::FULL_SCOPE_ROLES;

    public function hasFullScope(User $user): bool
    {
        return $user->hasAnyRole(self::FULL_SCOPE_ROLES);
    }

    /**
     * The single authoritative resolver for which clusters a supervisor may see/act on.
     * Full-scope roles (root/super_admin/admin_estate/property_manager) are exempt (see
     * everything). Always live-queried, never a cached snapshot.
     */
    public function clusterIdsFor(User $supervisor): array
    {
        if ($this->hasFullScope($supervisor)) {
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
     * Subquery (select collector_id) collector yang assignment aktif & berlakunya menyentuh
     * cluster supervisor: scope cluster/block (cluster_id), scope unit (unit di cluster), dan
     * scope resident (resident pemilik unit di cluster). Dipakai sebagai `whereIn(col, subquery)`
     * supaya tidak membangun daftar id raksasa di PHP.
     */
    public function collectorIdsQuery(User $supervisor): Builder
    {
        if ($this->hasFullScope($supervisor)) {
            return CollectorAssignment::query()->active()->currentlyEffective()->select('collector_id');
        }

        return $this->collectorQueryForClusters($this->clusterIdsFor($supervisor));
    }

    private function collectorQueryForClusters(array $clusterIds): Builder
    {
        return CollectorAssignment::query()->active()->currentlyEffective()->select('collector_id')->where(function (Builder $q) use ($clusterIds) {
            $q->whereIn('cluster_id', $clusterIds)
                ->orWhereIn('unit_id', Unit::query()->whereIn('cluster_id', $clusterIds)->select('id'))
                ->orWhereIn('resident_id', Unit::query()
                    ->whereIn('cluster_id', $clusterIds)
                    ->whereNotNull('resident_id')
                    ->select('resident_id'));
        });
    }

    /**
     * Collectors whose OWN active assignments touch any cluster this supervisor oversees
     * (termasuk assignment scope resident). Always derived from clusterIdsFor(), never
     * resolved independently.
     *
     * @return list<int>
     */
    public function collectorIdsFor(User $supervisor): array
    {
        if ($this->hasFullScope($supervisor)) {
            $query = $this->collectorIdsQuery($supervisor);
        } else {
            $clusterIds = $this->clusterIdsFor($supervisor);
            if (! $clusterIds) {
                return [];
            }
            $query = $this->collectorQueryForClusters($clusterIds);
        }

        return $query
            ->distinct()
            ->pluck('collector_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Subquery (select units.id) unit dalam cluster supervisor.
     */
    public function unitIdsQuery(User $supervisor): Builder
    {
        $query = Unit::query()->select('id');

        if ($this->hasFullScope($supervisor)) {
            return $query;
        }

        return $query->whereIn('cluster_id', $this->clusterIdsFor($supervisor));
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
        if ($this->hasFullScope($supervisor)) {
            return true;
        }

        return in_array($collectorId, $this->collectorIdsFor($supervisor), true);
    }

    public function assertCollectorAssigned(User $supervisor, int $collectorId): void
    {
        abort_unless($this->isCollectorAssigned($supervisor, $collectorId), 403, 'Kolektor ini bukan tanggung jawab Anda.');
    }
}
