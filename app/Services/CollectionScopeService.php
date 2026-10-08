<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Sumber tunggal cakupan data penagihan (collector, unit, cluster) per user:
 * - FULL_SCOPE_ROLES → semua data (null = tanpa batasan).
 * - supervisor → cluster dari SupervisorAssignmentService::clusterIdsFor(), collector yang
 *   assignment aktifnya menyentuh cluster tersebut (termasuk scope resident).
 * - collector → hanya dirinya & unit yang ditugaskan kepadanya.
 * - role lain → diperlakukan seperti supervisor (assignment supervisor-nya, biasanya kosong) —
 *   least privilege, konsisten dengan perilaku lama.
 *
 * Semua pembatasan query memakai subquery (bukan daftar id raksasa di PHP) bila memungkinkan.
 */
class CollectionScopeService
{
    public const FULL_SCOPE_ROLES = ['root', 'super_admin', 'admin_estate', 'property_manager'];

    private const MODE_FULL = 'full';

    private const MODE_CLUSTER = 'cluster';

    private const MODE_COLLECTOR = 'collector';

    public function __construct(
        private readonly SupervisorAssignmentService $supervisorScope,
        private readonly CollectorAssignmentService $collectorScope,
    ) {}

    public function isFullScope(User $user): bool
    {
        return $user->hasAnyRole(self::FULL_SCOPE_ROLES);
    }

    /** Collector "murni": dibatasi ke dirinya sendiri. Supervisor/full scope selalu menang. */
    public function isCollectorScope(User $user): bool
    {
        return $this->mode($user) === self::MODE_COLLECTOR;
    }

    /** @return list<string>|null null = semua cluster */
    public function clusterIdsFor(User $user): ?array
    {
        return match ($this->mode($user)) {
            self::MODE_FULL => null,
            self::MODE_COLLECTOR => $this->collectorClusterIds($user),
            default => array_values(array_map('strval', $this->supervisorScope->clusterIdsFor($user))),
        };
    }

    /** @return list<int>|null null = semua collector */
    public function collectorIdsFor(User $user): ?array
    {
        return match ($this->mode($user)) {
            self::MODE_FULL => null,
            self::MODE_COLLECTOR => [(int) $user->id],
            default => $this->supervisorScope->collectorIdsFor($user),
        };
    }

    /**
     * User ber-role collector (non-trashed) yang boleh dilihat user ini. Full scope = semua
     * collector, termasuk yang belum punya assignment.
     */
    public function allCollectorsQuery(User $user): EloquentBuilder
    {
        $query = User::query()->whereHas('roles', fn (EloquentBuilder $q) => $q->where('name', 'collector'));

        return $this->constrainCollectors($query, $user, 'users.id');
    }

    /** Batasi query ke unit dalam cakupan user (kolom berisi unit_id). */
    public function constrainUnits(EloquentBuilder|QueryBuilder $query, User $user, string $column = 'unit_id'): EloquentBuilder|QueryBuilder
    {
        return match ($this->mode($user)) {
            self::MODE_FULL => $query,
            self::MODE_COLLECTOR => $query->whereIn($column, $this->collectorScope->unitIdsFor($user)),
            default => $query->whereIn($column, $this->supervisorScope->unitIdsQuery($user)),
        };
    }

    /** Batasi query ke collector dalam cakupan user (kolom berisi collector user id). */
    public function constrainCollectors(EloquentBuilder|QueryBuilder $query, User $user, string $column): EloquentBuilder|QueryBuilder
    {
        return match ($this->mode($user)) {
            self::MODE_FULL => $query,
            self::MODE_COLLECTOR => $query->where($column, $user->id),
            default => $query->whereIn($column, $this->supervisorScope->collectorIdsQuery($user)),
        };
    }

    public function canAccessCollector(User $user, int $collectorId): bool
    {
        $ids = $this->collectorIdsFor($user);

        return $ids === null || in_array($collectorId, $ids, true);
    }

    public function canAccessUnit(User $user, string $unitId): bool
    {
        return match ($this->mode($user)) {
            self::MODE_FULL => true,
            self::MODE_COLLECTOR => $this->collectorScope->isUnitAssigned($user, $unitId),
            default => Unit::query()
                ->whereKey($unitId)
                ->whereIn('cluster_id', $this->supervisorScope->clusterIdsFor($user))
                ->exists(),
        };
    }

    /** null = "semua cluster/tanpa cluster" → hanya full scope yang boleh. */
    public function canAccessCluster(User $user, ?string $clusterId): bool
    {
        $ids = $this->clusterIdsFor($user);
        if ($ids === null) {
            return true;
        }

        return $clusterId !== null && $clusterId !== '' && in_array($clusterId, $ids, true);
    }

    public function assertCollectorInScope(User $user, int $collectorId): void
    {
        abort_unless($this->canAccessCollector($user, $collectorId), 403, 'Kolektor ini bukan tanggung jawab Anda.');
    }

    public function assertUnitInScope(User $user, string $unitId): void
    {
        abort_unless($this->canAccessUnit($user, $unitId), 403, 'Unit ini di luar cakupan Anda.');
    }

    public function assertClusterInScope(User $user, ?string $clusterId): void
    {
        abort_unless($this->canAccessCluster($user, $clusterId), 403, 'Cluster ini di luar cakupan Anda.');
    }

    private function mode(User $user): string
    {
        if ($this->isFullScope($user)) {
            return self::MODE_FULL;
        }

        if (! $user->hasRole('supervisor') && $user->hasRole('collector')) {
            return self::MODE_COLLECTOR;
        }

        return self::MODE_CLUSTER;
    }

    /** @return list<string> */
    private function collectorClusterIds(User $collector): array
    {
        $unitIds = $this->collectorScope->unitIdsFor($collector);
        if (! $unitIds) {
            return [];
        }

        return Unit::query()->whereIn('id', $unitIds)->distinct()->pluck('cluster_id')
            ->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
    }
}
