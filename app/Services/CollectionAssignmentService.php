<?php

namespace App\Services;

use App\Models\CollectionActivity;
use App\Models\CollectorAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Logika bersama penugasan collector: normalisasi scope, guard duplikat (keputusan #7),
 * pembuatan assignment, pemindahan (reassign, keputusan #5), cek cakupan supervisor, dan
 * pencatatan timeline penagihan. Dipakai CollectorAssignmentController (manual/edit/reassign),
 * CollectionAssignmentController (preview/bulk) dan alur serah-terima collector.
 *
 * Method yang menulis (create/transfer) TIDAK membuka transaksi sendiri - pemanggil wajib
 * membungkusnya dengan DB::transaction supaya multi-tulis tetap atomik.
 */
class CollectionAssignmentService
{
    public const SCOPE_TYPES = ['cluster', 'block', 'unit', 'resident'];

    public const SCOPE_FIELDS = ['scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly CollectionActivityService $activityService,
        private readonly CollectionScopeService $scopeService,
    ) {}

    /**
     * Hanya field yang relevan untuk scope_type yang disimpan; sisanya null. Dengan begitu dua
     * assignment dengan cakupan sama selalu punya tuple identik (dasar guard duplikat).
     *
     * @return array{scope_type: string, cluster_id: ?string, block: ?string, unit_id: ?string, resident_id: ?string}
     */
    public function normalizeScope(array $data): array
    {
        $type = (string) ($data['scope_type'] ?? '');
        $value = fn (string $key) => filled($data[$key] ?? null) ? trim((string) $data[$key]) : null;

        return [
            'scope_type' => $type,
            'cluster_id' => in_array($type, ['cluster', 'block'], true) ? $value('cluster_id') : null,
            'block' => $type === 'block' ? $value('block') : null,
            'unit_id' => $type === 'unit' ? $value('unit_id') : null,
            'resident_id' => $type === 'resident' ? $value('resident_id') : null,
        ];
    }

    /**
     * Assignment aktif dengan scope IDENTIK yang periodenya tumpang tindih dengan [start, end]
     * (null = terbuka). Overlap lintas level (cluster vs unit) sengaja tidak dihitung.
     */
    public function overlappingQuery(array $scope, ?string $start, ?string $end, array $ignoreIds = []): Builder
    {
        $scope = $this->normalizeScope($scope);

        $query = CollectorAssignment::query()->active()->where('scope_type', $scope['scope_type']);
        foreach (['cluster_id', 'block', 'unit_id', 'resident_id'] as $field) {
            $scope[$field] === null ? $query->whereNull($field) : $query->where($field, $scope[$field]);
        }

        return $query
            ->when($ignoreIds !== [], fn (Builder $q) => $q->whereNotIn('id', $ignoreIds))
            // Sisi yang terbuka (null) selalu tumpang tindih, jadi klausa hanya ditambah bila
            // rentang baru benar-benar dibatasi. whereDate() - lihat scopeCurrentlyEffective().
            ->when($end, fn (Builder $q, $value) => $q->where(fn (Builder $qq) => $qq->whereNull('start_date')->orWhereDate('start_date', '<=', $value)))
            ->when($start, fn (Builder $q, $value) => $q->where(fn (Builder $qq) => $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $value)));
    }

    /**
     * Guard duplikat (#7): tolak bila collector yang sama sudah memegang scope identik yang
     * aktif & overlap, dan tolak bila collector LAIN memegangnya (arahkan ke reassign).
     */
    public function assertNoConflict(int $collectorId, array $scope, ?string $start, ?string $end, array $ignoreIds = [], string $field = 'scope_type'): void
    {
        $holders = $this->overlappingQuery($scope, $start, $end, $ignoreIds)
            ->with('collector:id,name')
            ->get(['id', 'collector_id']);

        if ($holders->contains(fn (CollectorAssignment $row) => (int) $row->collector_id === $collectorId)) {
            throw ValidationException::withMessages([
                $field => 'Penugasan aktif dengan cakupan dan periode yang tumpang tindih sudah ada untuk kolektor ini.',
            ]);
        }

        if ($other = $holders->first()) {
            $name = $other->collector?->name ?? "#{$other->collector_id}";

            throw ValidationException::withMessages([
                $field => "Cakupan ini sedang dipegang oleh {$name}. Gunakan fitur Pindahkan (reassign) untuk mengalihkan penugasan tersebut.",
            ]);
        }
    }

    /**
     * Buat satu assignment aktif (guard duplikat dijalankan di sini), audit, dan catat timeline
     * untuk scope unit.
     *
     * @param  array{collector_id: int, scope_type: string, cluster_id?: ?string, block?: ?string, unit_id?: ?string, resident_id?: ?string, start_date?: ?string, end_date?: ?string, priority?: ?string, notes?: ?string}  $data
     */
    public function create(array $data, User $actor, bool $guard = true): CollectorAssignment
    {
        $collectorId = (int) $data['collector_id'];
        $scope = $this->normalizeScope($data);
        $start = $this->dateString($data['start_date'] ?? null) ?? Carbon::today()->toDateString();
        $end = $this->dateString($data['end_date'] ?? null);

        if ($guard) {
            $this->assertNoConflict($collectorId, $scope, $start, $end);
        }

        $assignment = CollectorAssignment::query()->create([
            ...$scope,
            'collector_id' => $collectorId,
            'is_active' => true,
            'status' => CollectorAssignment::STATUS_ACTIVE,
            'start_date' => $start,
            'end_date' => $end,
            'priority' => ($data['priority'] ?? null) ?: 'normal',
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
            'assigned_by' => $actor->id,
        ]);

        $this->auditService->log('collector_assignment_created', 'collector-assignments', 'CREATE', $assignment, [], $assignment->toArray());

        $assignment->loadMissing('collector:id,name');
        $this->recordTimeline($assignment, 'assigned', 'Ditugaskan ke '.($assignment->collector?->name ?? "kolektor #{$collectorId}"), $actor);

        return $assignment;
    }

    /**
     * Pindahkan assignment ke collector lain (#5): re-fetch dengan lockForUpdate, 422 bila sudah
     * tidak aktif, guard duplikat untuk collector tujuan, row lama → transferred, row baru dengan
     * reassigned_from_id & reassign_reason. Harus dipanggil di dalam DB::transaction.
     *
     * @param  array{start_date?: ?string, end_date?: ?string, priority?: ?string, notes?: ?string}  $overrides
     * @param  list<int>  $ignoreIds  row lain yang ikut dipindahkan/diakhiri dalam operasi yang sama
     */
    public function transfer(CollectorAssignment $assignment, int $newCollectorId, string $reason, User $actor, array $overrides = [], array $ignoreIds = []): CollectorAssignment
    {
        /** @var CollectorAssignment $old */
        $old = CollectorAssignment::query()->whereKey($assignment->getKey())->lockForUpdate()->firstOrFail();

        if (! $old->is_active || $old->status !== CollectorAssignment::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'assignment' => 'Penugasan ini sudah tidak aktif sehingga tidak dapat dipindahkan.',
            ]);
        }

        if ((int) $old->collector_id === $newCollectorId) {
            throw ValidationException::withMessages([
                'new_collector_id' => 'Kolektor tujuan harus berbeda dari kolektor saat ini.',
            ]);
        }

        $today = Carbon::today()->toDateString();
        $start = $this->dateString($overrides['start_date'] ?? null) ?? $today;
        $end = array_key_exists('end_date', $overrides)
            ? $this->dateString($overrides['end_date'])
            : ($old->end_date && $old->end_date->toDateString() >= $start ? $old->end_date->toDateString() : null);

        $scope = $old->only(self::SCOPE_FIELDS);
        $this->assertNoConflict($newCollectorId, $scope, $start, $end, [...$ignoreIds, $old->id], 'new_collector_id');

        $oldCollectorName = $old->collector()->value('name');
        $before = $old->toArray();
        $old->update([
            'is_active' => false,
            'status' => CollectorAssignment::STATUS_TRANSFERRED,
            'end_date' => $today,
        ]);

        $new = CollectorAssignment::query()->create([
            ...$scope,
            'collector_id' => $newCollectorId,
            'is_active' => true,
            'status' => CollectorAssignment::STATUS_ACTIVE,
            'start_date' => $start,
            'end_date' => $end,
            // priority bisa null di memori bila row lama dibuat mengandalkan default kolom DB.
            'priority' => ($overrides['priority'] ?? null) ?: ($old->priority ?: 'normal'),
            'notes' => filled($overrides['notes'] ?? null) ? $overrides['notes'] : null,
            'assigned_by' => $actor->id,
            'reassigned_from_id' => $old->id,
            'reassign_reason' => $reason,
        ]);

        $newCollectorName = $new->collector()->value('name');
        $this->auditService->log('collector_assignment_transferred', 'collector-assignments', 'UPDATE', $new, $before, [
            'old_assignment_id' => $old->id,
            'new_assignment_id' => $new->id,
            'old_collector' => ['id' => (int) $old->collector_id, 'name' => $oldCollectorName],
            'new_collector' => ['id' => $newCollectorId, 'name' => $newCollectorName],
            'changed_by' => ['id' => $actor->id, 'name' => $actor->name],
            'changed_at' => now()->toDateTimeString(),
            'reason' => $reason,
            'scope' => $scope,
        ], 'success', $reason);

        $this->recordTimeline(
            $new,
            'transferred',
            'Dipindahkan dari '.($oldCollectorName ?? "kolektor #{$old->collector_id}").' ke '.($newCollectorName ?? "kolektor #{$newCollectorId}"),
            $actor,
            ['reason' => $reason, 'from_collector_id' => (int) $old->collector_id, 'from_assignment_id' => $old->id],
        );

        return $new;
    }

    /** Akhiri satu assignment aktif tanpa pengganti (status transferred/cancelled). */
    public function close(CollectorAssignment $assignment, string $status, ?string $reason = null): void
    {
        $before = $assignment->toArray();
        $assignment->update([
            'is_active' => false,
            'status' => $status,
            'end_date' => Carbon::today()->toDateString(),
        ]);
        $this->auditService->log('collector_assignment_revoked', 'collector-assignments', 'DELETE', $assignment, $before, $assignment->refresh()->toArray(), 'success', $reason);
    }

    /**
     * Cakupan supervisor: target penugasan harus berada di cluster yang ia awasi. Full scope
     * bebas. Scope `units` memeriksa setiap unit.
     */
    public function assertScopeAccessible(User $user, array $scope): void
    {
        if ($this->scopeService->isFullScope($user)) {
            return;
        }

        $type = $scope['scope_type'] ?? null;

        if (in_array($type, ['cluster', 'block'], true)) {
            $this->scopeService->assertClusterInScope($user, $scope['cluster_id'] ?? null);

            return;
        }

        if ($type === 'unit') {
            $this->scopeService->assertUnitInScope($user, (string) ($scope['unit_id'] ?? ''));

            return;
        }

        $unitIds = match ($type) {
            'resident' => Unit::query()->where('resident_id', $scope['resident_id'] ?? null)->pluck('id')->all(),
            'units' => array_values(array_unique(array_map('strval', $scope['unit_ids'] ?? []))),
            default => [],
        };

        abort_if($unitIds === [], 403, 'Cakupan ini di luar wilayah Anda.');

        $allowed = $this->scopeService->constrainUnits(Unit::query()->whereIn('units.id', $unitIds), $user, 'units.id')->count();
        abort_if($allowed !== count($unitIds), 403, 'Sebagian unit berada di luar cakupan Anda.');
    }

    /**
     * Batasi query CollectorAssignment ke row yang menyentuh cakupan user: full scope = semua,
     * collector = miliknya sendiri, supervisor = row yang cluster/unit/resident-nya ada di cluster
     * yang ia awasi.
     */
    public function constrainAssignments(Builder $query, User $user): Builder
    {
        if ($this->scopeService->isFullScope($user)) {
            return $query;
        }

        if ($this->scopeService->isCollectorScope($user)) {
            return $query->where('collector_assignments.collector_id', $user->id);
        }

        return $this->whereTouchesClusters($query, $this->scopeService->clusterIdsFor($user) ?? []);
    }

    /** Row yang cluster/unit/resident-nya berada di salah satu cluster ini. */
    public function whereTouchesClusters(Builder $query, array $clusterIds, ?string $block = null): Builder
    {
        $units = fn () => Unit::query()->whereIn('cluster_id', $clusterIds)->when($block !== null, fn ($q) => $q->where('block', $block));

        return $query->where(function (Builder $q) use ($clusterIds, $block, $units) {
            // Filter blok juga mencakup assignment seluruh cluster yang otomatis meliputi blok itu.
            $q->where(fn (Builder $qq) => $qq->whereIn('collector_assignments.cluster_id', $clusterIds)
                ->when($block !== null, fn ($b) => $b->where(fn ($bb) => $bb
                    ->where('collector_assignments.block', $block)
                    ->orWhere('collector_assignments.scope_type', 'cluster'))))
                ->orWhereIn('collector_assignments.unit_id', $units()->select('id'))
                ->orWhereIn('collector_assignments.resident_id', $units()->whereNotNull('resident_id')->select('resident_id'));
        });
    }

    /** Assignment-assignment yang mencakup satu unit (scope unit/resident/block/cluster). */
    public function whereCoversUnit(Builder $query, Unit $unit): Builder
    {
        return $query->where(function (Builder $q) use ($unit) {
            $q->where(fn (Builder $qq) => $qq->where('scope_type', 'unit')->where('unit_id', $unit->id))
                ->orWhere(fn (Builder $qq) => $qq->where('scope_type', 'cluster')->where('cluster_id', $unit->cluster_id))
                ->orWhere(fn (Builder $qq) => $qq->where('scope_type', 'block')->where('cluster_id', $unit->cluster_id)->where('block', $unit->block))
                ->when($unit->resident_id, fn (Builder $qq) => $qq->orWhere(fn (Builder $r) => $r->where('scope_type', 'resident')->where('resident_id', $unit->resident_id)));
        });
    }

    /**
     * Catat aktivitas timeline TYPE_ASSIGNMENT untuk assignment scope unit. Refresh cache
     * ditangani observer assignment (refresh:false). Tidak pernah menggagalkan penugasan.
     */
    private function recordTimeline(CollectorAssignment $assignment, string $event, string $summary, User $actor, array $details = []): void
    {
        if ($assignment->scope_type !== 'unit' || ! $assignment->unit_id) {
            return;
        }

        try {
            $this->activityService->record($assignment->unit_id, CollectionActivity::TYPE_ASSIGNMENT, [
                'event' => $event,
                'summary' => $summary,
                'collector_id' => (int) $assignment->collector_id,
                'subject' => $assignment,
                'details' => ['assignment_id' => $assignment->id, ...$details],
            ], $actor, refresh: false);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    /** @return Collection<int, CollectorAssignment> */
    public function activeUnitHolders(array $unitIds, ?string $start, ?string $end): Collection
    {
        return CollectorAssignment::query()
            ->active()
            ->where('scope_type', 'unit')
            ->whereIn('unit_id', $unitIds)
            ->when($end, fn (Builder $q, $value) => $q->where(fn (Builder $qq) => $qq->whereNull('start_date')->orWhereDate('start_date', '<=', $value)))
            ->when($start, fn (Builder $q, $value) => $q->where(fn (Builder $qq) => $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $value)))
            ->with('collector:id,name')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
