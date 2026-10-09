<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\CollectionAccountState;
use App\Models\CollectorAssignment;
use App\Models\CollectorLocation;
use App\Models\CollectorProfile;
use App\Models\CollectorTarget;
use App\Models\CollectorVisit;
use App\Models\ManagedFile;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\Unit;
use App\Models\User;
use App\Rules\ActiveCollector;
use App\Services\AuditService;
use App\Services\CollectionAssignmentService;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use App\Services\CollectorPerformanceService;
use App\Support\Pagination;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CollectorController extends Controller
{
    use ApiResponse;

    private const ACCOUNT_STATUSES = ['active', 'inactive', 'leave', 'suspended'];

    private const EMPLOYMENT_STATUSES = ['tetap', 'kontrak', 'harian'];

    private const ACTION_REASSIGN = 'reassign';

    private const ACTION_END = 'end';

    /** Field profil yang boleh ditulis lewat store/update (selain collector_code & account_status). */
    private const PROFILE_FIELDS = [
        'whatsapp_number', 'address', 'joined_at', 'employment_status',
        'working_area_notes', 'admin_notes', 'duty_start_time', 'duty_end_time',
    ];

    private const EMPTY_STATS = [
        'assigned_accounts' => 0,
        'outstanding_total' => 0.0,
        'overdue_accounts' => 0,
        'critical_accounts' => 0,
        'active_assignment_count' => 0,
        'collected_this_month' => 0.0,
        'target_this_month' => null,
        'achievement_percent_raw' => null,
    ];

    public function __construct(
        private readonly CollectionScopeService $scope,
        private readonly CollectionAssignmentService $assignments,
    ) {}

    public function index(Request $request)
    {
        $viewer = $request->user();

        $query = $this->scope->allCollectorsQuery($viewer)
            ->with(['collectorProfile.latestPhoto'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$value}%")
                ->orWhere('username', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%")
                ->orWhereHas('collectorProfile', fn ($p) => $p->where('collector_code', 'like', "%{$value}%"))))
            ->when($request->query('account_status'), fn ($q, $value) => $q->whereHas('collectorProfile', fn ($p) => $p->where('account_status', $value)))
            ->when($request->query('employment_status'), fn ($q, $value) => $q->whereHas('collectorProfile', fn ($p) => $p->where('employment_status', $value)))
            ->when($request->query('cluster_id'), fn ($q, $clusterId) => $q->whereIn('users.id', $this->assignments->whereTouchesClusters(
                CollectorAssignment::query()->active()->currentlyEffective(),
                [(string) $clusterId],
            )->select('collector_assignments.collector_id')));

        $page = $query->latest()->paginate(Pagination::perPage($request));
        $collectors = $page->getCollection();
        $stats = $this->statsFor($collectors->pluck('id')->map(fn ($id) => (int) $id)->all(), $viewer);

        $collectors->each(function (User $collector) use ($viewer, $stats) {
            $this->presentProfile($collector, $viewer);
            $collector->setAttribute('stats', $stats[(int) $collector->id] ?? self::EMPTY_STATS);
        });

        return $this->paginated($page);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateCollector($request);
        $this->assertDutyHours($data, null);
        $autoCode = blank($data['collector_code'] ?? null);

        // Kode COL-NNNN dibuat di dalam transaksi dengan lock; bila tetap bentrok (dua request
        // bersamaan), ulangi dengan kode berikutnya.
        for ($attempt = 1; ; $attempt++) {
            try {
                $collector = DB::transaction(fn () => $this->createCollector($data, $request->user()));
                break;
            } catch (UniqueConstraintViolationException $e) {
                if (! $autoCode || $attempt >= 3) {
                    throw $e;
                }
            }
        }

        $auditService->log('collector_created', 'collectors', 'CREATE', $collector, [], $collector->toArray());

        $collector->load(['collectorProfile.photos', 'collectorProfile.latestPhoto', 'roles']);
        $this->presentProfile($collector, $request->user());

        return $this->success($collector, 'Kolektor berhasil ditambahkan.', 201);
    }

    public function show(Request $request, User $collector, CollectorAssignmentService $assignmentService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $viewer = $request->user();
        $this->assertViewable($viewer, $collector);

        $collector->load(['collectorProfile.photos', 'collectorProfile.latestPhoto']);
        $this->presentProfile($collector, $viewer);

        // Supervisor hanya melihat irisan data (penugasan/kunjungan/PTP) yang berada di clusternya.
        $slice = $this->slicesToScope($viewer);

        $assignments = CollectorAssignment::query()
            ->forCollector($collector->id)
            ->with(['cluster', 'unit.resident', 'resident', 'reassignedFrom.collector:id,name', 'assignedBy:id,name'])
            ->currentlyEffective()
            ->active()
            ->when($slice, fn (Builder $q) => $this->assignments->constrainAssignments($q, $viewer))
            ->latest()
            ->get();

        $unitIds = $assignmentService->unitIdsFor($collector);
        if ($slice && $unitIds) {
            $unitIds = $this->scope->constrainUnits(Unit::query()->whereIn('units.id', $unitIds), $viewer, 'units.id')
                ->pluck('units.id')->all();
        }

        $stats = $this->statsFor([(int) $collector->id], $viewer, withActivity: true)[(int) $collector->id] ?? self::EMPTY_STATS;
        $states = $this->stateQuery([(int) $collector->id], $viewer);

        $visits = fn () => $this->sliceUnits(CollectorVisit::query()->where('collector_id', $collector->id), $viewer, $slice);
        $complaints = fn () => $this->sliceUnits(ResidentComplaint::query()->where('collector_id', $collector->id), $viewer, $slice);
        $promises = fn () => PaymentPromise::query()->whereIn('unit_id', $unitIds);

        $assignmentIds = CollectorAssignment::query()
            ->forCollector($collector->id)
            ->when($slice, fn (Builder $q) => $this->assignments->constrainAssignments($q, $viewer))
            ->pluck('id');

        return $this->success([
            'collector' => $collector,
            'assignments' => $assignments,
            'summary' => [
                ...$stats,
                'total_residents' => $unitIds ? Unit::query()->whereIn('id', $unitIds)->whereNotNull('resident_id')->distinct()->count('resident_id') : 0,
                'total_units' => count($unitIds),
                'total_outstanding' => (float) $stats['outstanding_total'],
                'total_outstanding_invoices' => (int) (clone $states)->sum('open_invoice_count'),
                'total_payments_collected' => $this->collectedAllTime((int) $collector->id),
                'total_payment_promises' => $promises()->count(),
                'total_visits' => $visits()->count(),
                'total_complaints' => $complaints()->count(),
            ],
            'recent_visits' => $visits()->with('unit.cluster')->latest('visit_date')->limit(10)->get(),
            'recent_payment_promises' => $promises()->with('unit.cluster')->latest('promised_date')->limit(10)->get(),
            'recent_complaints' => $complaints()->with('unit.cluster')->latest()->limit(10)->get(),
            'latest_location' => CollectorLocation::query()->where('collector_id', $collector->id)->latest('recorded_at')->first(),
            'targets' => CollectorTarget::query()->where('collector_id', $collector->id)->latest('period_start')->limit(6)->get(),
            'assignment_history' => AuditLog::query()
                ->where('entity_type', CollectorAssignment::class)
                ->whereIn('entity_id', $assignmentIds)
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    /**
     * PUT partial-safe: hanya key yang dikirim yang ditulis. Mengubah username mencabut semua
     * token. account_status non-aktif lewat PUT mengikuti aturan serah terima yang sama dengan
     * PATCH /status (422 + active_assignment_count bila masih ada penugasan aktif).
     */
    public function update(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $this->scope->assertCollectorInScope($request->user(), (int) $collector->id);

        $data = $this->validateCollector($request, $collector);
        $profile = $collector->collectorProfile;
        $this->assertDutyHours($data, $profile);
        $old = $collector->load('collectorProfile')->toArray();

        $newStatus = $data['account_status'] ?? null;
        $statusChanged = $newStatus !== null && $newStatus !== ($profile?->account_status ?? ($collector->is_active ? CollectorProfile::STATUS_ACTIVE : CollectorProfile::STATUS_INACTIVE));
        $deactivating = $statusChanged && $newStatus !== CollectorProfile::STATUS_ACTIVE;
        $lifecycle = $deactivating ? $this->validateLifecycle($request, $collector) : [];
        $usernameChanged = array_key_exists('username', $data) && $data['username'] !== $collector->username;

        $handover = DB::transaction(function () use ($request, $collector, $data, $newStatus, $deactivating, $lifecycle) {
            $handover = $deactivating ? $this->handleActiveAssignments($request->user(), $collector, $lifecycle) : null;

            $userAttributes = array_intersect_key($data, array_flip(['name', 'username', 'email', 'phone']));
            if (! empty($data['password'])) {
                $userAttributes['password'] = Hash::make($data['password']);
            }
            if ($newStatus !== null) {
                $userAttributes['is_active'] = $newStatus === CollectorProfile::STATUS_ACTIVE;
            }
            if ($userAttributes) {
                $collector->update($userAttributes);
            }

            $profile = $this->ensureProfile($collector, $request->user());
            $profileAttributes = $this->profileAttributes($data);
            if (array_key_exists('collector_code', $data) && filled($data['collector_code'])) {
                $profileAttributes['collector_code'] = $data['collector_code'];
            }
            if ($newStatus !== null) {
                $profileAttributes['account_status'] = $newStatus;
            }
            $profile->fill($profileAttributes)->forceFill(['updated_by' => $request->user()->id])->save();

            return $handover;
        });

        if ($usernameChanged || $deactivating) {
            $collector->revokeApiTokens();
        }

        $collector->refresh()->load('collectorProfile');
        $auditService->log('collector_updated', 'collectors', 'UPDATE', $collector, $old, [
            ...$collector->toArray(),
            ...($handover ? ['assignment_handover' => $handover] : []),
        ], 'success', $lifecycle['reason'] ?? null);

        $collector->load(['collectorProfile.photos', 'collectorProfile.latestPhoto', 'roles']);
        $this->presentProfile($collector, $request->user());

        return $this->success($collector, 'Kolektor berhasil diperbarui.');
    }

    public function destroy(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $this->scope->assertCollectorInScope($request->user(), (int) $collector->id);

        $lifecycle = $this->validateLifecycle($request, $collector);
        $old = $collector->toArray();

        $handover = DB::transaction(function () use ($request, $collector, $lifecycle) {
            $handover = $this->handleActiveAssignments($request->user(), $collector, $lifecycle);
            $collector->revokeApiTokens();
            $collector->delete();

            return $handover;
        });

        $auditService->log('collector_deleted', 'collectors', 'DELETE', $collector, $old, $handover ? ['assignment_handover' => $handover] : [], 'success', $lifecycle['reason'] ?? null);

        return $this->success(null, 'Kolektor berhasil dihapus.');
    }

    public function updateStatus(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $this->scope->assertCollectorInScope($request->user(), (int) $collector->id);

        $data = $request->validate([
            'account_status' => ['required', Rule::in(self::ACCOUNT_STATUSES)],
        ]);
        $deactivating = $data['account_status'] !== CollectorProfile::STATUS_ACTIVE;
        $lifecycle = $this->validateLifecycle($request, $collector, $deactivating);
        $reason = $lifecycle['reason'] ?? null;

        $old = $collector->load('collectorProfile')->toArray();

        $handover = DB::transaction(function () use ($request, $collector, $data, $deactivating, $lifecycle, $reason) {
            $handover = $deactivating ? $this->handleActiveAssignments($request->user(), $collector, $lifecycle) : null;

            $collector->forceFill(['is_active' => ! $deactivating])->save();
            $profile = $this->ensureProfile($collector, $request->user());
            $profile->forceFill([
                'account_status' => $data['account_status'],
                'admin_notes' => filled($reason)
                    ? trim(($profile->admin_notes ?? '')."\n[".now()->toDateTimeString()."] {$reason}")
                    : $profile->admin_notes,
                'updated_by' => $request->user()->id,
            ])->save();

            return $handover;
        });

        if ($deactivating) {
            $collector->revokeApiTokens();
        }

        $collector->refresh()->load('collectorProfile');
        $auditService->log('collector_status_changed', 'collectors', 'UPDATE_STATUS', $collector, $old, [
            ...$collector->toArray(),
            ...($handover ? ['assignment_handover' => $handover] : []),
        ], 'success', $reason);

        $collector->load('collectorProfile.latestPhoto');
        $this->presentProfile($collector, $request->user());

        return $this->success($collector, 'Status kolektor berhasil diperbarui.');
    }

    public function assignmentHistory(Request $request, User $collector)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $viewer = $request->user();
        $this->assertViewable($viewer, $collector);

        $assignmentIds = CollectorAssignment::query()
            ->forCollector($collector->id)
            ->when($this->slicesToScope($viewer), fn (Builder $q) => $this->assignments->constrainAssignments($q, $viewer))
            ->select('id');
        $query = AuditLog::query()->where('entity_type', CollectorAssignment::class)->whereIn('entity_id', $assignmentIds);

        return $this->paginated($query->latest()->paginate(Pagination::perPage($request, 20)));
    }

    public function uploadPhoto(Request $request, User $collector)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $this->scope->assertCollectorInScope($request->user(), (int) $collector->id);
        $data = $request->validate(['photo' => ['required', 'file', 'image', 'max:5120']]);

        $profile = $this->ensureProfile($collector, $request->user());
        $stored = $data['photo']->store('collector-photos', 'public');
        $file = ManagedFile::query()->create([
            'original_filename' => $data['photo']->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $data['photo']->getMimeType(),
            'extension' => $data['photo']->getClientOriginalExtension(),
            'size' => $data['photo']->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => CollectorProfile::class,
            'entity_id' => $profile->id,
        ]);

        return $this->success($file, 'Foto profil berhasil diunggah.', 201);
    }

    // ---- Lifecycle (keputusan #4) ----

    /**
     * Validasi payload serah terima. `reason` wajib (5..500) bila `assignment_action` dikirim;
     * tanpa itu tetap opsional (catatan perubahan status).
     *
     * @return array{assignment_action?: ?string, reassign_to_collector_id?: mixed, reason?: ?string}
     */
    private function validateLifecycle(Request $request, User $collector, bool $allowAction = true): array
    {
        $withAction = $allowAction && $request->filled('assignment_action');

        return $request->validate([
            'assignment_action' => ['nullable', Rule::in([self::ACTION_REASSIGN, self::ACTION_END])],
            'reassign_to_collector_id' => [
                'nullable',
                Rule::requiredIf($withAction && $request->input('assignment_action') === self::ACTION_REASSIGN),
                Rule::notIn([$collector->id]),
                new ActiveCollector,
            ],
            'reason' => $withAction
                ? ['required', 'string', 'min:5', 'max:500']
                : ['nullable', 'string', 'max:500'],
        ], [
            'assignment_action.in' => 'Tindakan penugasan harus reassign atau end.',
            'reassign_to_collector_id.required' => 'Pilih kolektor tujuan untuk memindahkan penugasan.',
            'reassign_to_collector_id.not_in' => 'Kolektor tujuan harus berbeda dari kolektor ini.',
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 5 karakter.',
        ]);
    }

    /** Assignment yang masih aktif dan belum berakhir (termasuk yang dijadwalkan mulai nanti). */
    private function activeAssignmentsQuery(int|array $collectorIds): Builder
    {
        $today = Carbon::today()->toDateString();

        return CollectorAssignment::query()
            ->whereIn('collector_id', (array) $collectorIds)
            ->where('is_active', true)
            ->where('status', CollectorAssignment::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }

    /**
     * Tolak (422 + errors.active_assignment_count) bila collector masih memegang penugasan aktif
     * tanpa `assignment_action`; bila ada, pindahkan (reassign) atau akhiri (end) semuanya.
     * Harus dipanggil di dalam DB::transaction.
     *
     * @return array{action: string, count: int, reassign_to_collector_id?: int, assignment_ids: list<int>, new_assignment_ids?: list<int>}|null
     */
    private function handleActiveAssignments(User $actor, User $collector, array $lifecycle): ?array
    {
        $active = $this->activeAssignmentsQuery((int) $collector->id)->orderBy('id')->lockForUpdate()->get();
        if ($active->isEmpty()) {
            return null;
        }

        $action = $lifecycle['assignment_action'] ?? null;
        if (! $action) {
            throw new HttpResponseException($this->error(
                "Kolektor masih memegang {$active->count()} penugasan aktif. Pindahkan ke kolektor lain atau akhiri penugasan terlebih dahulu.",
                422,
                ['active_assignment_count' => $active->count()],
            ));
        }

        $ids = $active->pluck('id')->map(fn ($id) => (int) $id)->all();
        $reason = (string) $lifecycle['reason'];

        if ($action === self::ACTION_END) {
            foreach ($active as $assignment) {
                $this->assignments->close($assignment, CollectorAssignment::STATUS_CANCELLED, $reason);
            }

            return ['action' => $action, 'count' => count($ids), 'assignment_ids' => $ids];
        }

        $targetId = (int) $lifecycle['reassign_to_collector_id'];
        $today = Carbon::today()->toDateString();
        $newIds = [];

        try {
            foreach ($active as $assignment) {
                $start = $assignment->start_date && $assignment->start_date->toDateString() > $today
                    ? $assignment->start_date->toDateString()
                    : $today;
                $newIds[] = (int) $this->assignments->transfer($assignment, $targetId, $reason, $actor, ['start_date' => $start], $ids)->id;
            }
        } catch (ValidationException $e) {
            // Pesan guard duplikat memakai key endpoint reassign; petakan ke field form serah terima.
            $errors = $e->errors();
            if (isset($errors['new_collector_id'])) {
                $errors['reassign_to_collector_id'] = $errors['new_collector_id'];
                unset($errors['new_collector_id']);
            }
            throw ValidationException::withMessages($errors);
        }

        return [
            'action' => $action,
            'count' => count($ids),
            'reassign_to_collector_id' => $targetId,
            'assignment_ids' => $ids,
            'new_assignment_ids' => $newIds,
        ];
    }

    // ---- Stats / summary ----

    /**
     * Statistik per collector, batched: beban akun dari collection_account_states (diiris ke
     * cakupan supervisor), jumlah penugasan aktif, dan metrik bulan berjalan.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function statsFor(array $ids, User $viewer, bool $withActivity = false): array
    {
        if (! $ids) {
            return [];
        }

        $states = $this->stateQuery($ids, $viewer)
            ->toBase()
            ->selectRaw('collector_id, COUNT(*) as assigned_accounts, COALESCE(SUM(outstanding_total), 0) as outstanding_total')
            ->selectRaw('SUM(CASE WHEN aging_days > 0 AND outstanding_total > 0 THEN 1 ELSE 0 END) as overdue_accounts')
            ->selectRaw('SUM(CASE WHEN priority_level = ? THEN 1 ELSE 0 END) as critical_accounts', [CollectionAccountState::PRIORITY_CRITICAL])
            ->groupBy('collector_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->collector_id);

        $assignmentCounts = $this->activeAssignmentsQuery($ids)
            ->toBase()
            ->selectRaw('collector_id, COUNT(*) as total')
            ->groupBy('collector_id')
            ->pluck('total', 'collector_id');

        $metrics = $this->monthMetrics($ids);

        $result = [];
        foreach ($ids as $id) {
            $state = $states->get($id);
            $metric = $metrics[$id] ?? [];

            $row = [
                'assigned_accounts' => (int) ($state->assigned_accounts ?? 0),
                'outstanding_total' => round((float) ($state->outstanding_total ?? 0), 2),
                'overdue_accounts' => (int) ($state->overdue_accounts ?? 0),
                'critical_accounts' => (int) ($state->critical_accounts ?? 0),
                'active_assignment_count' => (int) ($assignmentCounts[$id] ?? 0),
                'collected_this_month' => round((float) ($metric['collected_amount'] ?? 0), 2),
                'target_this_month' => isset($metric['target_amount']) ? (float) $metric['target_amount'] : null,
                'achievement_percent_raw' => $metric['achievement_percent_raw'] ?? null,
            ];

            if ($withActivity) {
                $row += [
                    'achievement_percent' => $metric['achievement_percent'] ?? null,
                    'visit_count' => (int) ($metric['visit_count'] ?? 0),
                    'successful_visit_rate' => $metric['successful_visit_rate'] ?? null,
                    'ptp_created' => (int) ($metric['ptp_created'] ?? 0),
                    'ptp_fulfilled' => (int) ($metric['ptp_fulfilled'] ?? 0),
                    'ptp_broken' => (int) ($metric['ptp_broken'] ?? 0),
                    'period' => $metric['period'] ?? null,
                ];
            }

            $result[$id] = $row;
        }

        return $result;
    }

    /** Query collection_account_states milik collector (collector utama), diiris ke cakupan viewer. */
    private function stateQuery(array $ids, User $viewer): Builder
    {
        $query = CollectionAccountState::query()->whereIn('collector_id', $ids);

        return $this->slicesToScope($viewer) ? $this->scope->constrainUnits($query, $viewer, 'unit_id') : $query;
    }

    /**
     * Metrik bulan berjalan per collector. Memakai CollectorPerformanceService::metricsFor()
     * (aturan collected §7) bila tersedia; cadangan: query collected + target bulanan langsung.
     *
     * @param  list<int>  $ids
     * @return array<int, array<string, mixed>>
     */
    private function monthMetrics(array $ids): array
    {
        $performance = app(CollectorPerformanceService::class);

        if (method_exists($performance, 'resolvePeriod') && method_exists($performance, 'metricsFor')) {
            try {
                $period = $performance->resolvePeriod(CollectorTarget::PERIOD_MONTHLY, now()->toDateString());
                $payload = ['type' => $period['type'], 'start' => $this->dateOf($period['start']), 'end' => $this->dateOf($period['end'])];

                return collect($performance->metricsFor($ids, $period))
                    ->map(fn (array $row) => [...$row, 'period' => $payload])
                    ->all();
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->fallbackMonthMetrics($ids);
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackMonthMetrics(array $ids): array
    {
        $start = CarbonImmutable::now()->startOfMonth();
        $end = $start->endOfMonth();

        $collected = $this->collectedQuery($ids)
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('COALESCE(collected_by, created_by) as collector_id, SUM(total) as collected_amount')
            ->groupByRaw('COALESCE(collected_by, created_by)')
            ->pluck('collected_amount', 'collector_id');

        $targets = CollectorTarget::query()
            ->whereIn('collector_id', $ids)
            ->where('period_type', CollectorTarget::PERIOD_MONTHLY)
            ->whereDate('period_start', $start->toDateString())
            ->pluck('target_amount', 'collector_id');

        $visits = CollectorVisit::query()
            ->toBase()
            ->whereIn('collector_id', $ids)
            ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('collector_id, COUNT(*) as total')
            ->groupBy('collector_id')
            ->pluck('total', 'collector_id');

        $result = [];
        foreach ($ids as $id) {
            $amount = round((float) ($collected[$id] ?? 0), 2);
            $target = isset($targets[$id]) ? (float) $targets[$id] : null;
            $raw = $target > 0 ? round($amount / $target * 100, 1) : null;

            $result[$id] = [
                'collected_amount' => $amount,
                'target_amount' => $target,
                'achievement_percent_raw' => $raw,
                'achievement_percent' => $raw === null ? null : min($raw, 100.0),
                'visit_count' => (int) ($visits[$id] ?? 0),
                'period' => ['type' => CollectorTarget::PERIOD_MONTHLY, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
            ];
        }

        return $result;
    }

    /**
     * Aturan collected (§7): status paid dan (collected_by = collector ATAU (collected_by kosong,
     * provider loket, dibuat oleh collector)).
     */
    private function collectedQuery(array $ids): \Illuminate\Database\Query\Builder
    {
        return PaymentTransaction::query()
            ->toBase()
            ->where('status', 'paid')
            ->where(fn ($q) => $q->whereIn('collected_by', $ids)
                ->orWhere(fn ($legacy) => $legacy->whereNull('collected_by')->where('payment_provider', 'loket')->whereIn('created_by', $ids)));
    }

    private function collectedAllTime(int $collectorId): float
    {
        return round((float) $this->collectedQuery([$collectorId])->sum('total'), 2);
    }

    private function dateOf(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10);
    }

    // ---- Scope & presentasi ----

    /** Viewer non-full-scope yang bukan collector itu sendiri → data diiris ke clusternya. */
    private function slicesToScope(User $viewer): bool
    {
        return ! $this->scope->isFullScope($viewer) && ! $this->scope->isCollectorScope($viewer);
    }

    private function sliceUnits(Builder $query, User $viewer, bool $slice): Builder
    {
        return $slice ? $this->scope->constrainUnits($query, $viewer, 'unit_id') : $query;
    }

    private function assertViewable(User $viewer, User $collector): void
    {
        if ($this->scope->isCollectorScope($viewer)) {
            abort_unless($viewer->id === $collector->id, 403, 'Anda hanya dapat melihat profil Anda sendiri.');

            return;
        }

        $this->scope->assertCollectorInScope($viewer, (int) $collector->id);
    }

    /** Tambahkan photo_url, sembunyikan admin_notes untuk viewer collector. */
    private function presentProfile(User $collector, User $viewer): void
    {
        $profile = $collector->collectorProfile;
        if (! $profile) {
            return;
        }

        $profile->append('photo_url');

        if ($this->scope->isCollectorScope($viewer)) {
            $profile->makeHidden('admin_notes');
        }
    }

    // ---- Create / profile helpers ----

    private function createCollector(array $data, User $actor): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'is_active' => $data['account_status'] === CollectorProfile::STATUS_ACTIVE,
        ]);
        $user->assignRole('collector');

        $profile = CollectorProfile::query()->create([
            ...$this->profileAttributes($data),
            'user_id' => $user->id,
            'collector_code' => ($data['collector_code'] ?? null) ?: $this->generateCollectorCode(),
            'employment_status' => ($data['employment_status'] ?? null) ?: CollectorProfile::EMPLOYMENT_PERMANENT,
            'account_status' => $data['account_status'],
            'created_by' => $actor->id,
        ]);

        if (! empty($data['initial_monthly_target'])) {
            CollectorTarget::query()->create([
                'collector_id' => $user->id,
                'period_type' => CollectorTarget::PERIOD_MONTHLY,
                'period_start' => now()->startOfMonth()->toDateString(),
                'target_amount' => $data['initial_monthly_target'],
                'created_by' => $actor->id,
            ]);
        }

        return $user->setRelation('collectorProfile', $profile);
    }

    /** Profil collector; dibuat bila belum ada (akun lama tanpa profil tidak lagi 500). */
    private function ensureProfile(User $collector, User $actor): CollectorProfile
    {
        $profile = $collector->collectorProfile()->first();
        if ($profile) {
            return $profile;
        }

        $profile = CollectorProfile::query()->create([
            'user_id' => $collector->id,
            'collector_code' => $this->generateCollectorCode(),
            'employment_status' => CollectorProfile::EMPLOYMENT_PERMANENT,
            'account_status' => $collector->is_active ? CollectorProfile::STATUS_ACTIVE : CollectorProfile::STATUS_INACTIVE,
            'created_by' => $actor->id,
        ]);
        $collector->setRelation('collectorProfile', $profile);

        return $profile;
    }

    /** Hanya field profil yang ADA di payload (partial-safe), jam tugas dinormalisasi ke H:i:s. */
    private function profileAttributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(self::PROFILE_FIELDS));

        foreach (['duty_start_time', 'duty_end_time'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $this->normalizeTime($attributes[$field]);
            }
        }

        if (array_key_exists('employment_status', $attributes) && blank($attributes['employment_status'])) {
            $attributes['employment_status'] = CollectorProfile::EMPLOYMENT_PERMANENT;
        }

        return $attributes;
    }

    private function normalizeTime(?string $value): ?string
    {
        return filled($value) ? substr($value, 0, 5).':00' : null;
    }

    /**
     * Jam tugas: selesai wajib bila mulai diisi dan harus setelah mulai. Pada PUT parsial,
     * nilai yang tidak dikirim diambil dari profil saat ini.
     */
    private function assertDutyHours(array $data, ?CollectorProfile $profile): void
    {
        if (! array_key_exists('duty_start_time', $data) && ! array_key_exists('duty_end_time', $data)) {
            return;
        }

        $start = array_key_exists('duty_start_time', $data) ? $data['duty_start_time'] : $profile?->duty_start_time;
        $end = array_key_exists('duty_end_time', $data) ? $data['duty_end_time'] : $profile?->duty_end_time;
        $start = filled($start) ? substr((string) $start, 0, 5) : null;
        $end = filled($end) ? substr((string) $end, 0, 5) : null;

        if ($start !== null && $end === null) {
            throw ValidationException::withMessages(['duty_end_time' => 'Jam selesai wajib diisi bila jam mulai diisi.']);
        }

        if ($start !== null && $end <= $start) {
            throw ValidationException::withMessages(['duty_end_time' => 'Jam selesai harus setelah jam mulai.']);
        }
    }

    /** COL-NNNN berikutnya; baris COL-% dikunci (lockForUpdate) selama transaksi pembuatan. */
    private function generateCollectorCode(): string
    {
        $max = CollectorProfile::query()
            ->where('collector_code', 'like', 'COL-%')
            ->lockForUpdate()
            ->pluck('collector_code')
            ->map(fn (string $code) => (int) substr($code, 4))
            ->max() ?? 0;

        return 'COL-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function validateCollector(Request $request, ?User $collector = null): array
    {
        // Saat update semua field memakai `sometimes`: key yang tidak dikirim tidak divalidasi
        // dan tidak ditulis (PUT parsial tidak menghapus data).
        $sometimes = $collector ? ['sometimes'] : [];
        $time = ['nullable', 'date_format:H:i,H:i:s'];

        return $request->validate([
            'name' => [...$sometimes, 'required', 'string', 'max:100'],
            'username' => [...$sometimes, 'required', 'string', 'max:50', Rule::unique('users')->ignore($collector?->id)],
            'email' => [...$sometimes, 'nullable', 'email', 'max:100', Rule::unique('users')->ignore($collector?->id)],
            'phone' => [...$sometimes, 'nullable', 'string', 'max:20'],
            'password' => [$collector ? 'nullable' : 'required', 'string', 'min:8'],
            'collector_code' => [...$sometimes, 'nullable', 'string', 'max:20', Rule::unique('collector_profiles')->ignore($collector?->collectorProfile?->id)],
            'whatsapp_number' => [...$sometimes, 'nullable', 'string', 'max:30'],
            'address' => [...$sometimes, 'nullable', 'string'],
            'joined_at' => [...$sometimes, 'nullable', 'date'],
            'employment_status' => [...$sometimes, 'nullable', Rule::in(self::EMPLOYMENT_STATUSES)],
            'account_status' => [...$sometimes, 'required', Rule::in(self::ACCOUNT_STATUSES)],
            'working_area_notes' => [...$sometimes, 'nullable', 'string'],
            'admin_notes' => [...$sometimes, 'nullable', 'string'],
            'duty_start_time' => [...$sometimes, ...$time],
            'duty_end_time' => [...$sometimes, ...$time],
            'initial_monthly_target' => [...$sometimes, 'nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'duty_start_time.date_format' => 'Format jam mulai harus HH:mm.',
            'duty_end_time.date_format' => 'Format jam selesai harus HH:mm.',
        ]);
    }
}
