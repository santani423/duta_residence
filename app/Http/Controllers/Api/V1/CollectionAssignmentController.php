<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectionAccountState;
use App\Models\CollectorAssignment;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Rules\ActiveCollector;
use App\Services\AuditService;
use App\Services\CollectionAssignmentService;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use App\Support\Pagination;
use App\Support\UnitFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Penugasan massal: preview cakupan, bulk/area assign, dan daftar unit belum ditugaskan.
 */
class CollectionAssignmentController extends Controller
{
    use ApiResponse;

    public const MAX_UNITS = 500;

    public function __construct(
        private readonly CollectionAssignmentService $assignments,
        private readonly CollectorAssignmentService $resolver,
        private readonly CollectionScopeService $scope,
    ) {}

    /**
     * Ringkasan cakupan sebelum menugaskan: jumlah unit (resolver live), tunggakan dari cache
     * collection_account_states, dan collector yang saat ini memegang unit-unit tersebut.
     */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in([...CollectionAssignmentService::SCOPE_TYPES, 'units'])],
            'cluster_id' => ['required_if:scope_type,cluster,block', 'nullable', 'exists:clusters,id'],
            'block' => ['required_if:scope_type,block', 'nullable', 'string', 'max:5'],
            'unit_id' => ['required_if:scope_type,unit', 'nullable', 'string'],
            'resident_id' => ['required_if:scope_type,resident', 'nullable', 'exists:residents,id'],
            'unit_ids' => ['required_if:scope_type,units', 'array', 'max:'.self::MAX_UNITS],
            'unit_ids.*' => ['string', 'max:5'],
        ]);

        $scope = $data['scope_type'] === 'units'
            ? ['scope_type' => 'units', 'unit_ids' => array_values(array_unique($data['unit_ids'] ?? []))]
            : $this->assignments->normalizeScope($data);

        $unitIds = $this->resolver->unitIdsForScope($scope);
        if ($data['scope_type'] === 'unit') {
            // unitIdsForScope tidak memeriksa keberadaan unit untuk scope unit.
            $unitIds = Unit::query()->whereIn('id', $unitIds)->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        $this->assignments->assertScopeAccessible(
            $request->user(),
            $data['scope_type'] === 'units' ? ['scope_type' => 'units', 'unit_ids' => $unitIds] : $scope,
        );

        $summary = ['outstanding_total' => 0.0, 'overdue_accounts' => 0, 'critical_accounts' => 0];
        $holders = [];

        foreach (array_chunk($unitIds, 1000) as $chunk) {
            $row = CollectionAccountState::query()
                ->whereIn('unit_id', $chunk)
                ->selectRaw('COALESCE(SUM(outstanding_total), 0) as outstanding_total')
                ->selectRaw('SUM(CASE WHEN outstanding_total > 0 AND aging_days > 0 THEN 1 ELSE 0 END) as overdue_accounts')
                ->selectRaw('SUM(CASE WHEN outstanding_total > 0 AND priority_level = ? THEN 1 ELSE 0 END) as critical_accounts', [CollectionAccountState::PRIORITY_CRITICAL])
                ->toBase()
                ->first();

            $summary['outstanding_total'] += (float) ($row->outstanding_total ?? 0);
            $summary['overdue_accounts'] += (int) ($row->overdue_accounts ?? 0);
            $summary['critical_accounts'] += (int) ($row->critical_accounts ?? 0);

            $units = Unit::query()->whereIn('id', $chunk)->get(['id', 'cluster_id', 'block', 'resident_id']);
            foreach ($this->resolver->primaryCollectorIdsFor($units) as $collectorId) {
                $holders[$collectorId] = ($holders[$collectorId] ?? 0) + 1;
            }
        }

        $names = User::query()->whereIn('id', array_keys($holders))->pluck('name', 'id');
        $currentCollectors = collect($holders)
            ->map(fn (int $count, int $collectorId) => [
                'collector_id' => $collectorId,
                'name' => $names[$collectorId] ?? "#{$collectorId}",
                'unit_count' => $count,
            ])
            ->sortBy([['unit_count', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return $this->success([
            'unit_count' => count($unitIds),
            'outstanding_total' => round($summary['outstanding_total'], 2),
            'overdue_accounts' => $summary['overdue_accounts'],
            'critical_accounts' => $summary['critical_accounts'],
            'unassigned_count' => count($unitIds) - array_sum($holders),
            'current_collectors' => $currentCollectors,
        ]);
    }

    /**
     * mode `units`: satu assignment scope unit per unit; unit yang sudah dipegang collector ini
     * dilewati, yang dipegang collector lain menjadi konflik (atau dipindahkan bila
     * transfer_conflicts). mode `area`: satu assignment cluster/blok. Semua dalam satu transaksi.
     */
    public function bulk(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'collector_id' => ['required', new ActiveCollector],
            'mode' => ['required', Rule::in(['units', 'area'])],
            'unit_ids' => ['required_if:mode,units', 'array', 'min:1', 'max:'.self::MAX_UNITS],
            'unit_ids.*' => ['string', 'max:5', 'distinct'],
            'cluster_id' => ['required_if:mode,area', 'nullable', 'exists:clusters,id'],
            'block' => ['nullable', 'string', 'max:5'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['nullable', Rule::in(CollectionAssignmentService::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'transfer_conflicts' => ['nullable', 'boolean'],
            'reason' => [Rule::requiredIf(fn () => $request->boolean('transfer_conflicts')), 'nullable', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pemindahan wajib diisi untuk memindahkan unit dari kolektor lain.',
        ]);

        $user = $request->user();
        $collectorId = (int) $data['collector_id'];
        $schedule = [
            'start_date' => filled($data['start_date'] ?? null) ? Carbon::parse($data['start_date'])->toDateString() : Carbon::today()->toDateString(),
            'end_date' => filled($data['end_date'] ?? null) ? Carbon::parse($data['end_date'])->toDateString() : null,
            'priority' => ($data['priority'] ?? null) ?: 'normal',
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
        ];

        $result = $data['mode'] === 'area'
            ? $this->bulkArea($data, $collectorId, $schedule, $user)
            : $this->bulkUnits($data, $collectorId, $schedule, $user);

        $auditService->log('collector_assignment_bulk', 'collector-assignments', 'CREATE', null, [], [
            'mode' => $data['mode'],
            'collector_id' => $collectorId,
            'cluster_id' => $data['cluster_id'] ?? null,
            'block' => $data['block'] ?? null,
            'unit_count' => count($data['unit_ids'] ?? []),
            'transfer_conflicts' => (bool) ($data['transfer_conflicts'] ?? false),
            'reason' => $data['reason'] ?? null,
            ...$schedule,
            'result' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'transferred' => $result['transferred'],
                'conflict_count' => count($result['conflicts']),
            ],
        ]);

        $changed = $result['created'] !== [] || $result['transferred'] !== [];
        $message = $changed ? 'Penugasan massal berhasil disimpan.' : 'Tidak ada penugasan baru yang dibuat.';

        return $this->success($result, $message, $changed ? 201 : 200);
    }

    /**
     * Unit (non-deleted) yang tidak tercakup assignment aktif & berlaku mana pun, di-scope
     * cluster supervisor. Cakupan dihitung LIVE dari collector_assignments (bukan dari cache
     * collection_account_states.collector_id) supaya unit baru/cache basi tidak salah tampil;
     * tunggakan/status/prioritas diambil dari cache (LEFT JOIN - unit tanpa cache tetap ikut).
     */
    public function unassignedUnits(Request $request)
    {
        $request->validate([
            'has_outstanding' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer'],
        ]);

        $query = Unit::query()
            ->leftJoin('collection_account_states as cas', 'cas.unit_id', '=', 'units.id')
            ->leftJoin('clusters', 'clusters.id', '=', 'units.cluster_id')
            ->select('units.*', 'clusters.name as cluster_name', 'cas.outstanding_total as cas_outstanding_total', 'cas.status as cas_status', 'cas.priority_level as cas_priority_level', 'cas.customer_resident_id as cas_customer_resident_id')
            ->whereNotExists(fn (QueryBuilder $q) => $this->coveringAssignmentQuery($q))
            // Filter & pencarian lewat subquery: query utama ber-JOIN sehingga kolom tanpa
            // prefix tabel (mis. `id` di scope search) akan ambigu.
            ->tap(fn (Builder $q) => UnitFilters::apply($q, $request, 'units.id'))
            ->when(trim((string) $request->query('search')), fn (Builder $q, string $search) => $q->whereIn('units.id', Unit::query()->search($search)->select('id')));

        if ($request->filled('has_outstanding')) {
            $request->boolean('has_outstanding')
                ? $query->where('cas.outstanding_total', '>', 0)
                : $query->where(fn (Builder $q) => $q->whereNull('cas.outstanding_total')->orWhere('cas.outstanding_total', '<=', 0));
        }

        $this->scope->constrainUnits($query, $request->user(), 'units.id');

        $page = $query
            ->orderByRaw('COALESCE(cas.outstanding_total, 0) DESC')
            ->orderBy('units.id')
            ->paginate(Pagination::perPage($request));

        $residentIds = collect($page->items())
            ->map(fn (Unit $unit) => $unit->cas_customer_resident_id ?: $unit->billingPayerResidentId())
            ->filter()
            ->unique()
            ->values();
        $residents = Resident::query()->whereIn('id', $residentIds)->get(['id', 'name', 'phone'])->keyBy('id');

        $page->setCollection($page->getCollection()->map(function (Unit $unit) use ($residents) {
            $customer = $residents->get($unit->cas_customer_resident_id ?: $unit->billingPayerResidentId());

            return [
                'unit_id' => $unit->id,
                'cluster_id' => $unit->cluster_id,
                'cluster_name' => $unit->cluster_name,
                'block' => $unit->block,
                'lot_number' => $unit->lot_number,
                'customer' => $customer ? ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone] : null,
                'outstanding_total' => round((float) ($unit->cas_outstanding_total ?? 0), 2),
                'status' => $unit->cas_status,
                'priority_level' => $unit->cas_priority_level,
            ];
        }));

        return $this->paginated($page);
    }

    private function bulkUnits(array $data, int $collectorId, array $schedule, User $user): array
    {
        $requested = array_values(array_unique(array_map('strval', $data['unit_ids'])));
        $existing = Unit::query()->whereIn('id', $requested)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $missing = array_values(array_diff($requested, $existing));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'unit_ids' => 'Unit tidak ditemukan: '.implode(', ', array_slice($missing, 0, 20)).(count($missing) > 20 ? ' …' : ''),
            ]);
        }

        // Pertahankan urutan input supaya hasil (created/skipped) mengikuti pilihan pengguna.
        $unitIds = array_values(array_intersect($requested, $existing));
        $this->assignments->assertScopeAccessible($user, ['scope_type' => 'units', 'unit_ids' => $unitIds]);

        $transfer = (bool) ($data['transfer_conflicts'] ?? false);
        $reason = isset($data['reason']) ? trim($data['reason']) : null;

        return DB::transaction(function () use ($unitIds, $collectorId, $schedule, $user, $transfer, $reason) {
            $holders = $this->assignments
                ->activeUnitHolders($unitIds, $schedule['start_date'], $schedule['end_date'])
                ->groupBy('unit_id');

            $result = ['created' => [], 'skipped' => [], 'conflicts' => [], 'transferred' => []];

            foreach ($unitIds as $unitId) {
                /** @var Collection<int, CollectorAssignment> $rows */
                $rows = $holders->get($unitId, collect());

                if ($rows->contains(fn (CollectorAssignment $row) => (int) $row->collector_id === $collectorId)) {
                    $result['skipped'][] = $unitId;

                    continue;
                }

                if ($rows->isEmpty()) {
                    $result['created'][] = $this->assignments->create([
                        'collector_id' => $collectorId,
                        'scope_type' => 'unit',
                        'unit_id' => $unitId,
                        ...$schedule,
                    ], $user, guard: false)->id;

                    continue;
                }

                if (! $transfer) {
                    foreach ($rows as $row) {
                        $result['conflicts'][] = [
                            'unit_id' => $unitId,
                            'collector_id' => (int) $row->collector_id,
                            'collector_name' => $row->collector?->name,
                            'assignment_id' => $row->id,
                        ];
                    }

                    continue;
                }

                $ignore = $rows->pluck('id')->all();
                $first = $rows->shift();
                $result['transferred'][] = $this->assignments->transfer($first, $collectorId, $reason, $user, $schedule, $ignore)->id;

                // Data lama bisa berisi lebih dari satu pemegang scope identik; sisanya diakhiri.
                foreach ($rows as $row) {
                    $this->assignments->close($row, CollectorAssignment::STATUS_TRANSFERRED, $reason);
                }
            }

            return $result;
        });
    }

    private function bulkArea(array $data, int $collectorId, array $schedule, User $user): array
    {
        $scope = $this->assignments->normalizeScope([
            'scope_type' => filled($data['block'] ?? null) ? 'block' : 'cluster',
            'cluster_id' => $data['cluster_id'],
            'block' => $data['block'] ?? null,
        ]);

        $this->assignments->assertScopeAccessible($user, $scope);

        $assignment = DB::transaction(fn () => $this->assignments->create([
            'collector_id' => $collectorId,
            ...$scope,
            ...$schedule,
        ], $user));

        return ['created' => [$assignment->id], 'skipped' => [], 'conflicts' => [], 'transferred' => []];
    }

    /** Assignment aktif & berlaku hari ini yang mencakup baris `units` (korelasi NOT EXISTS). */
    private function coveringAssignmentQuery(QueryBuilder $query): QueryBuilder
    {
        $today = Carbon::today()->toDateString();

        return $query->select(DB::raw(1))
            ->from('collector_assignments as ca')
            ->where('ca.is_active', true)
            ->where(fn (QueryBuilder $q) => $q->whereNull('ca.start_date')->orWhereDate('ca.start_date', '<=', $today))
            ->where(fn (QueryBuilder $q) => $q->whereNull('ca.end_date')->orWhereDate('ca.end_date', '>=', $today))
            ->where(fn (QueryBuilder $q) => $q
                ->where(fn (QueryBuilder $s) => $s->where('ca.scope_type', 'unit')->whereColumn('ca.unit_id', 'units.id'))
                ->orWhere(fn (QueryBuilder $s) => $s->where('ca.scope_type', 'cluster')->whereColumn('ca.cluster_id', 'units.cluster_id'))
                ->orWhere(fn (QueryBuilder $s) => $s->where('ca.scope_type', 'block')->whereColumn('ca.cluster_id', 'units.cluster_id')->whereColumn('ca.block', 'units.block'))
                ->orWhere(fn (QueryBuilder $s) => $s->where('ca.scope_type', 'resident')->whereColumn('ca.resident_id', 'units.resident_id')));
    }
}
