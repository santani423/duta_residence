<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorTarget;
use App\Rules\CollectorUser;
use App\Services\AuditService;
use App\Services\CollectionScopeService;
use App\Services\CollectorPerformanceService;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectorTargetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CollectionScopeService $scope,
        private readonly CollectorPerformanceService $performance,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'collector_id' => ['nullable', 'integer'],
            'period_type' => ['nullable', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['nullable', 'date'],
        ]);

        $periodStart = $request->query('period_start');
        if ($periodStart && $request->query('period_type')) {
            $periodStart = $this->performance->resolvePeriod($request->query('period_type'), $periodStart)['start_date'];
        }

        $query = CollectorTarget::query()
            ->with('collector.collectorProfile')
            ->when($request->query('collector_id'), fn ($q, $value) => $q->where('collector_id', (int) $value))
            ->when($request->query('period_type'), fn ($q, $value) => $q->where('period_type', $value))
            ->when($periodStart, fn ($q, $value) => $q->whereDate('period_start', $value));

        $this->scope->constrainCollectors($query, $request->user(), 'collector_id');

        return $this->paginated($query->latest('period_start')->latest('id')->paginate(Pagination::perPage($request)));
    }

    /**
     * Progres semua collector dalam cakupan (termasuk yang belum punya target) untuk satu periode.
     */
    public function progress(Request $request)
    {
        $data = $request->validate([
            'period_type' => ['nullable', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['nullable', 'date'],
            'cluster_id' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();
        $clusterId = $data['cluster_id'] ?? null;
        if ($clusterId) {
            $this->scope->assertClusterInScope($user, $clusterId);
        }

        $period = $this->performance->resolvePeriod($data['period_type'] ?? CollectorTarget::PERIOD_MONTHLY, $data['period_start'] ?? null);

        $collectorsQuery = $this->scope->allCollectorsQuery($user)
            ->with('collectorProfile:id,user_id,collector_code,account_status')
            ->orderBy('name');
        if ($clusterId) {
            $this->performance->constrainCollectorsToCluster($collectorsQuery, $clusterId);
        }
        $collectors = $collectorsQuery->get(['users.id', 'users.name', 'users.is_active']);

        $ids = $collectors->pluck('id')->map(fn ($id) => (int) $id)->all();
        $metrics = $this->performance->metricsFor($ids, $period);
        $targets = $this->performance->targetsFor($ids, $period);

        $rows = $collectors->map(fn ($collector) => [
            'collector' => $this->performance->collectorSummary($collector),
            'target' => $targets->get((int) $collector->id),
            'metrics' => $metrics[(int) $collector->id] ?? null,
        ])->values();

        return $this->success($rows, 'Data berhasil ditemukan.', 200, [
            'period' => $this->performance->periodPayload($period),
            'total' => $rows->count(),
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateTarget($request);
        $data['created_by'] = $request->user()->id;

        $target = CollectorTarget::query()->create($data);
        $auditService->log('collector_target_created', 'collector-targets', 'CREATE', $target, [], $target->toArray());

        return $this->success($target->load('collector.collectorProfile'), 'Target kolektor berhasil dibuat.', 201);
    }

    public function update(Request $request, CollectorTarget $collectorTarget, AuditService $auditService)
    {
        $this->scope->assertCollectorInScope($request->user(), (int) $collectorTarget->collector_id);

        $data = $this->validateTarget($request, $collectorTarget);
        $old = $collectorTarget->toArray();
        $collectorTarget->update($data);
        $auditService->log('collector_target_updated', 'collector-targets', 'UPDATE', $collectorTarget, $old, $collectorTarget->refresh()->toArray());

        return $this->success($collectorTarget->load('collector.collectorProfile'), 'Target kolektor berhasil diperbarui.');
    }

    public function destroy(Request $request, CollectorTarget $collectorTarget, AuditService $auditService)
    {
        $this->scope->assertCollectorInScope($request->user(), (int) $collectorTarget->collector_id);

        $old = $collectorTarget->toArray();
        $collectorTarget->delete();
        $auditService->log('collector_target_deleted', 'collector-targets', 'DELETE', $collectorTarget, $old, []);

        return $this->success(null, 'Target kolektor berhasil dihapus.');
    }

    private function validateTarget(Request $request, ?CollectorTarget $target = null): array
    {
        $data = $request->validate([
            'collector_id' => ['required', new CollectorUser],
            'period_type' => ['required', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['required', 'date'],
            'target_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'target_visit_count' => ['nullable', 'integer', 'min:0'],
            'target_account_count' => ['nullable', 'integer', 'min:0'],
            'target_collection_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'target_amount.max' => 'Target nominal terlalu besar.',
            'target_collection_rate.min' => 'Target collection rate harus antara 0 dan 100.',
            'target_collection_rate.max' => 'Target collection rate harus antara 0 dan 100.',
        ]);

        $data['collector_id'] = (int) $data['collector_id'];
        $this->scope->assertCollectorInScope($request->user(), $data['collector_id']);

        // Selalu simpan awal periode (Senin / tanggal 1) supaya lookup target per periode konsisten.
        $data['period_start'] = $this->performance->resolvePeriod($data['period_type'], $data['period_start'])['start_date'];

        $duplicate = CollectorTarget::query()
            ->where('collector_id', $data['collector_id'])
            ->where('period_type', $data['period_type'])
            ->whereDate('period_start', $data['period_start'])
            ->when($target, fn ($q) => $q->whereKeyNot($target->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'period_start' => 'Target untuk kolektor dan periode ini sudah ada. Ubah target yang ada.',
            ]);
        }

        return $data;
    }
}
