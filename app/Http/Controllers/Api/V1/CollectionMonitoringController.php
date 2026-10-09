<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionAccountResource;
use App\Http\Responses\ApiResponse;
use App\Models\CollectionAccountState;
use App\Models\CollectorProfile;
use App\Models\Resident;
use App\Models\Unit;
use App\Rules\ActiveCollector;
use App\Services\CollectionAgingService;
use App\Services\CollectionScopeService;
use App\Support\Pagination;
use App\Support\UnitFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Monitoring akun penagihan lintas collector & opsi collector (sesuai scope).
 *
 * Basis data monitoring adalah cache `collection_account_states` (satu baris per unit), dibatasi ke
 * unit yang belum dihapus dan ke cakupan user lewat CollectionScopeService.
 */
class CollectionMonitoringController extends Controller
{
    use ApiResponse;

    /** Kolom yang boleh dipakai untuk `sort` (selain ini → 422). */
    public const SORTABLE = [
        'priority_score', 'outstanding_total', 'aging_days', 'oldest_due_date',
        'last_contact_at', 'next_follow_up_at', 'unit_id',
    ];

    /** Kolom sort yang bisa null — null selalu ditaruh paling akhir (MySQL & SQLite konsisten). */
    private const NULLABLE_SORT = ['oldest_due_date', 'last_contact_at', 'next_follow_up_at'];

    private const OPTIONS_LIMIT = 500;

    public function __construct(
        private readonly CollectionScopeService $scope,
        private readonly CollectionAgingService $aging,
    ) {}

    /** GET /collection/accounts */
    public function accounts(Request $request): JsonResponse
    {
        $this->normalizeListParams($request, ['status', 'aging_bucket', 'priority']);

        $validated = $request->validate([
            'collector_id' => ['nullable', 'integer', 'min:1'],
            'unassigned' => ['nullable', 'boolean'],
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(CollectionAccountState::STATUSES)],
            'aging_bucket' => ['nullable', 'array'],
            'aging_bucket.*' => ['string', Rule::in(array_keys(CollectionAgingService::BUCKETS))],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['string', Rule::in(CollectionAccountState::PRIORITY_LEVELS)],
            'min_outstanding' => ['nullable', 'numeric', 'min:0'],
            'max_outstanding' => ['nullable', 'numeric', 'min:0'],
            'has_outstanding' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'string', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer'],
        ], [
            'sort.in' => 'Kolom pengurutan tidak didukung.',
            'direction.in' => 'Arah pengurutan harus asc atau desc.',
            'status.*.in' => 'Status akun tidak dikenal.',
            'aging_bucket.*.in' => 'Kelompok umur tunggakan tidak dikenal.',
            'priority.*.in' => 'Level prioritas tidak dikenal.',
        ]);

        $user = $request->user();
        $collectorId = isset($validated['collector_id']) ? (int) $validated['collector_id'] : null;
        if ($collectorId !== null) {
            $this->scope->assertCollectorInScope($user, $collectorId);
        }

        $filtered = $this->filteredStates($request, $validated, $collectorId);

        $sort = $validated['sort'] ?? 'priority_score';
        $direction = $validated['direction'] ?? 'desc';

        $list = (clone $filtered)
            ->with([
                'unit' => fn ($q) => $q->select('id', 'cluster_id', 'block', 'lot_number'),
                'unit.cluster:id,name',
                'customer:id,name,phone',
                'collector:id,name',
            ])
            ->when(in_array($sort, self::NULLABLE_SORT, true), fn (Builder $q) => $q->orderByRaw("{$sort} IS NULL"))
            ->orderBy($sort, $direction)
            ->when($sort !== 'unit_id', fn (Builder $q) => $q->orderBy('unit_id'));

        $paginator = $list->paginate(Pagination::perPage($request));

        $aging = $this->aging->breakdown(clone $filtered);
        $summary = $this->summarize(clone $filtered, $aging);

        return response()->json([
            'success' => true,
            'message' => 'Data berhasil ditemukan.',
            'data' => CollectionAccountResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'sort' => $sort,
                'direction' => $direction,
                'summary' => $summary,
                'aging' => $aging,
            ],
            // Duplikat di level atas untuk konsumen yang membaca `summary`/`aging` langsung.
            'summary' => $summary,
            'aging' => $aging,
        ]);
    }

    /** GET /collection/collectors/options — daftar ringkas collector untuk dropdown, sesuai scope. */
    public function collectorOptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'include_inactive' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = $this->scope->allCollectorsQuery($request->user());

        if (! ($validated['include_inactive'] ?? false)) {
            ActiveCollector::constrain($query);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(fn (Builder $q) => $q
                ->where('users.name', 'like', "%{$search}%")
                ->orWhere('users.username', 'like', "%{$search}%")
                ->orWhereHas('collectorProfile', fn (Builder $p) => $p->where('collector_code', 'like', "%{$search}%")));
        }

        $collectors = $query
            ->with('collectorProfile:id,user_id,collector_code,account_status')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit(self::OPTIONS_LIMIT)
            ->get(['users.id', 'users.name', 'users.username', 'users.is_active']);

        return $this->success($collectors->map(fn ($collector) => [
            'id' => (int) $collector->id,
            'name' => $collector->name,
            'username' => $collector->username,
            'collector_code' => $collector->collectorProfile?->collector_code,
            // Collector lama tanpa profil dianggap aktif selama is_active (sama dengan ActiveCollector).
            'account_status' => $collector->collectorProfile?->account_status ?? CollectorProfile::STATUS_ACTIVE,
            'is_active' => (bool) $collector->is_active,
        ])->values()->all());
    }

    /**
     * Query states yang sudah difilter & di-scope, TANPA select/order — dipakai bersama oleh daftar,
     * ringkasan dan breakdown aging supaya angkanya selalu konsisten.
     *
     * @return Builder<CollectionAccountState>
     */
    private function filteredStates(Request $request, array $validated, ?int $collectorId): Builder
    {
        $query = CollectionAccountState::query()
            // Unit yang sudah dihapus (soft delete) tidak ikut dimonitor.
            ->whereIn('unit_id', Unit::query()->select('units.id'));

        $this->scope->constrainUnits($query, $request->user(), 'unit_id');
        UnitFilters::apply($query, $request, 'unit_id');

        if ($collectorId !== null) {
            $query->where('collector_id', $collectorId);
        } elseif ($request->boolean('unassigned')) {
            $query->whereNull('collector_id');
        }

        $query
            ->when($validated['status'] ?? null, fn (Builder $q, array $values) => $q->whereIn('status', $values))
            ->when($validated['aging_bucket'] ?? null, fn (Builder $q, array $values) => $q->whereIn('aging_bucket', $values))
            ->when($validated['priority'] ?? null, fn (Builder $q, array $values) => $q->whereIn('priority_level', $values))
            ->when(isset($validated['min_outstanding']), fn (Builder $q) => $q->where('outstanding_total', '>=', $validated['min_outstanding']))
            ->when(isset($validated['max_outstanding']), fn (Builder $q) => $q->where('outstanding_total', '<=', $validated['max_outstanding']));

        // Default hanya akun yang masih punya tunggakan; has_outstanding=0 → semua akun.
        if (! $request->filled('has_outstanding') || $request->boolean('has_outstanding')) {
            $query->where('outstanding_total', '>', 0);
        }

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(fn (Builder $q) => $q
                ->where('unit_id', 'like', $like)
                ->orWhereIn('customer_resident_id', Resident::query()
                    ->select('residents.id')
                    ->where(fn ($r) => $r->where('name', 'like', $like)->orWhere('phone', 'like', $like))));
        }

        return $query;
    }

    /** @param  list<array{bucket: string, account_count: int, outstanding_amount: float}>  $aging */
    private function summarize(Builder $filtered, array $aging): array
    {
        $totals = (clone $filtered)
            ->selectRaw('COUNT(*) as account_count, COALESCE(SUM(outstanding_total), 0) as outstanding_total')
            ->toBase()
            ->first();

        $byStatus = (clone $filtered)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $byPriority = (clone $filtered)
            ->selectRaw('priority_level, COUNT(*) as total')
            ->groupBy('priority_level')
            ->toBase()
            ->pluck('total', 'priority_level')
            ->map(fn ($count) => (int) $count)
            ->all();

        // Selalu sertakan semua level prioritas (0 bila tidak ada) supaya UI stabil.
        $byPriority = collect(CollectionAccountState::PRIORITY_LEVELS)
            ->mapWithKeys(fn (string $level) => [$level => $byPriority[$level] ?? 0])
            ->all() + $byPriority;

        $overdue = collect($aging)
            ->reject(fn (array $row) => $row['bucket'] === CollectionAgingService::BUCKET_CURRENT)
            ->sum('account_count');

        return [
            'account_count' => (int) ($totals->account_count ?? 0),
            'outstanding_total' => round((float) ($totals->outstanding_total ?? 0), 2),
            'by_status' => (object) $byStatus,
            'by_priority' => $byPriority,
            'overdue_accounts' => (int) $overdue,
            'critical_accounts' => (int) ($byPriority[CollectionAccountState::PRIORITY_CRITICAL] ?? 0),
        ];
    }

    /**
     * Terima `status=a,b` maupun `status[]=a&status[]=b` (axios) sebagai array; string kosong
     * diabaikan.
     */
    private function normalizeListParams(Request $request, array $keys): void
    {
        foreach ($keys as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            if (is_string($value)) {
                $value = explode(',', $value);
            }
            if (is_array($value)) {
                $value = array_values(array_filter(array_map(
                    fn ($item) => is_string($item) ? trim($item) : $item,
                    $value,
                ), fn ($item) => $item !== null && $item !== ''));
                $request->merge([$key => $value ?: null]);
            }
        }
    }
}
