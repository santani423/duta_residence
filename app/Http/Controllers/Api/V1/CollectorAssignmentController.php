<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorAssignment;
use App\Models\Unit;
use App\Rules\ActiveCollector;
use App\Services\AuditService;
use App\Services\CollectionAssignmentService;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectorAssignmentController extends Controller
{
    use ApiResponse;

    private const RELATIONS = [
        'collector:id,name',
        'cluster:id,name',
        'unit.cluster:id,name',
        'resident:id,name',
        'assignedBy:id,name',
        'reassignedFrom.collector:id,name',
    ];

    /** Field cakupan yang tidak boleh diubah lewat PUT (gunakan reassign / penugasan baru). */
    private const IMMUTABLE_FIELDS = ['collector_id', 'scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id'];

    public function __construct(private readonly CollectionAssignmentService $assignments) {}

    public function index(Request $request)
    {
        $query = CollectorAssignment::query()
            ->with(self::RELATIONS)
            ->when($request->query('collector_id'), fn ($q, $value) => $q->where('collector_id', $value))
            ->when($request->query('scope_type'), fn ($q, $value) => $q->where('scope_type', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('currently_effective'), fn ($q) => $q->active()->currentlyEffective())
            ->when($request->query('unit_id'), function (Builder $q, $unitId) {
                $unit = Unit::query()->find($unitId);

                return $unit ? $this->assignments->whereCoversUnit($q, $unit) : $q->where('unit_id', $unitId);
            })
            ->when(! $request->query('unit_id') && $request->query('cluster_id'), fn (Builder $q) => $this->assignments->whereTouchesClusters(
                $q,
                [(string) $request->query('cluster_id')],
                filled($request->query('block')) ? (string) $request->query('block') : null,
            ))
            ->when(! $request->query('unit_id') && ! $request->query('cluster_id') && filled($request->query('block')), function (Builder $q) use ($request) {
                $block = (string) $request->query('block');

                return $q->where(fn (Builder $qq) => $qq->where('block', $block)
                    ->orWhereIn('unit_id', Unit::query()->where('block', $block)->select('id')));
            })
            ->when(trim((string) $request->query('search')), function (Builder $q, string $search) {
                $like = "%{$search}%";

                return $q->where(fn (Builder $qq) => $qq
                    ->where('unit_id', 'like', $like)
                    ->orWhere('block', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('collector', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('resident', fn (Builder $r) => $r->where('name', 'like', $like))
                    ->orWhereHas('cluster', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('unit.resident', fn (Builder $r) => $r->where('name', 'like', $like)));
            });

        $this->assignments->constrainAssignments($query, $request->user());

        return $this->paginated($query->latest()->latest('id')->paginate(Pagination::perPage($request)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'collector_id' => ['required', new ActiveCollector],
            'scope_type' => ['required', Rule::in(CollectionAssignmentService::SCOPE_TYPES)],
            'cluster_id' => ['required_if:scope_type,cluster,block', 'nullable', 'exists:clusters,id'],
            'block' => ['required_if:scope_type,block', 'nullable', 'string', 'max:5'],
            'unit_id' => ['required_if:scope_type,unit', 'nullable', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'resident_id' => ['required_if:scope_type,resident', 'nullable', 'exists:residents,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['nullable', Rule::in(CollectionAssignmentService::PRIORITIES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $scope = $this->assignments->normalizeScope($data);
        $this->assignments->assertScopeAccessible($request->user(), $scope);

        $assignment = DB::transaction(fn () => $this->assignments->create([...$data, ...$scope], $request->user()));

        return $this->success($assignment->load(self::RELATIONS), 'Penugasan kolektor berhasil dibuat.', 201);
    }

    /**
     * Hanya jadwal, prioritas, catatan, dan status (active|completed) yang boleh diubah. Cakupan
     * dan kolektor immutable - perubahan kolektor lewat reassign agar riwayatnya tercatat.
     */
    public function update(Request $request, CollectorAssignment $collectorAssignment, AuditService $auditService)
    {
        $this->assignments->assertScopeAccessible($request->user(), $collectorAssignment->only(CollectionAssignmentService::SCOPE_FIELDS));
        $this->assertScopeUnchanged($request, $collectorAssignment);

        if (! in_array($collectorAssignment->status, [CollectorAssignment::STATUS_ACTIVE, CollectorAssignment::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Penugasan yang sudah dicabut atau dipindahkan tidak dapat diubah.',
            ]);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'required', Rule::in([CollectorAssignment::STATUS_ACTIVE, CollectorAssignment::STATUS_COMPLETED])],
            'priority' => ['sometimes', 'nullable', Rule::in(CollectionAssignmentService::PRIORITIES)],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $start = array_key_exists('start_date', $data) ? $this->dateOrNull($data['start_date']) : $collectorAssignment->start_date?->toDateString();
        $end = array_key_exists('end_date', $data) ? $this->dateOrNull($data['end_date']) : $collectorAssignment->end_date?->toDateString();
        if ($start && $end && $end < $start) {
            throw ValidationException::withMessages(['end_date' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        }

        $status = $data['status'] ?? $collectorAssignment->status;
        $isActive = $status === CollectorAssignment::STATUS_ACTIVE;

        if ($isActive) {
            $this->assignments->assertNoConflict(
                (int) $collectorAssignment->collector_id,
                $collectorAssignment->only(CollectionAssignmentService::SCOPE_FIELDS),
                $start,
                $end,
                [$collectorAssignment->id],
                'status',
            );
        }

        $changes = ['status' => $status, 'is_active' => $isActive, 'start_date' => $start, 'end_date' => $end];
        if (array_key_exists('priority', $data)) {
            $changes['priority'] = $data['priority'] ?: 'normal';
        }
        if (array_key_exists('notes', $data)) {
            $changes['notes'] = filled($data['notes']) ? $data['notes'] : null;
        }

        $old = $collectorAssignment->toArray();
        DB::transaction(fn () => $collectorAssignment->update($changes));
        $auditService->log('collector_assignment_updated', 'collector-assignments', 'UPDATE', $collectorAssignment, $old, $collectorAssignment->refresh()->toArray());

        return $this->success($collectorAssignment->load(self::RELATIONS), 'Penugasan kolektor berhasil diperbarui.');
    }

    public function destroy(Request $request, CollectorAssignment $collectorAssignment, AuditService $auditService)
    {
        $this->assignments->assertScopeAccessible($request->user(), $collectorAssignment->only(CollectionAssignmentService::SCOPE_FIELDS));

        if (! $collectorAssignment->is_active || $collectorAssignment->status !== CollectorAssignment::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'assignment' => 'Penugasan ini sudah tidak aktif.',
            ]);
        }

        $old = $collectorAssignment->toArray();
        DB::transaction(fn () => $collectorAssignment->update([
            'is_active' => false,
            'status' => CollectorAssignment::STATUS_CANCELLED,
            'end_date' => Carbon::today()->toDateString(),
        ]));
        $auditService->log('collector_assignment_revoked', 'collector-assignments', 'DELETE', $collectorAssignment, $old, $collectorAssignment->refresh()->toArray());

        return $this->success(null, 'Penugasan kolektor berhasil dicabut.');
    }

    /**
     * Pindahkan cakupan ke kolektor lain (#5): row lama → transferred, row baru aktif dengan
     * reassigned_from_id & reassign_reason, dalam satu transaksi. Audit
     * `collector_assignment_transferred` memuat kolektor lama/baru, pengubah, waktu, alasan.
     */
    public function reassign(Request $request, CollectorAssignment $collectorAssignment)
    {
        $this->assignments->assertScopeAccessible($request->user(), $collectorAssignment->only(CollectionAssignmentService::SCOPE_FIELDS));

        $data = $request->validate([
            'new_collector_id' => ['required', Rule::notIn([$collectorAssignment->collector_id]), new ActiveCollector],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'new_collector_id.not_in' => 'Kolektor tujuan harus berbeda dari kolektor saat ini.',
        ]);

        $newAssignment = DB::transaction(fn () => $this->assignments->transfer(
            $collectorAssignment,
            (int) $data['new_collector_id'],
            trim($data['reason']),
            $request->user(),
            array_filter([
                'start_date' => $data['start_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value) => filled($value)),
        ));

        return $this->success($newAssignment->load(self::RELATIONS), 'Penugasan berhasil dipindahkan.', 201);
    }

    private function assertScopeUnchanged(Request $request, CollectorAssignment $assignment): void
    {
        $changed = collect(self::IMMUTABLE_FIELDS)
            ->filter(fn (string $field) => $request->has($field))
            ->filter(function (string $field) use ($request, $assignment) {
                $sent = $request->input($field);
                $current = $assignment->getAttribute($field);

                return $this->normalize($sent) !== $this->normalize($current);
            })
            ->values();

        if ($changed->isNotEmpty()) {
            throw ValidationException::withMessages($changed->mapWithKeys(fn (string $field) => [
                $field => 'Kolektor dan cakupan penugasan tidak dapat diubah. Gunakan Pindahkan (reassign) atau buat penugasan baru.',
            ])->all());
        }
    }

    private function normalize(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function dateOrNull(mixed $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toDateString() : null;
    }
}
