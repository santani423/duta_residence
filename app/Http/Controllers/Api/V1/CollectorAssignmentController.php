<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CollectorAssignmentController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['collector', 'cluster', 'unit', 'resident', 'assignedBy'];

    public function index(Request $request)
    {
        $query = CollectorAssignment::query()
            ->with(self::RELATIONS)
            ->when($request->query('collector_id'), fn ($q, $value) => $q->where('collector_id', $value))
            ->when($request->query('scope_type'), fn ($q, $value) => $q->where('scope_type', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateAssignment($request);
        $this->assertNoDuplicateActiveScope($data);

        $data['assigned_by'] = $request->user()->id;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['status'] = $data['status'] ?? CollectorAssignment::STATUS_ACTIVE;

        $assignment = CollectorAssignment::query()->create($data);
        $auditService->log('collector_assignment_created', 'collector-assignments', 'CREATE', $assignment, [], $assignment->toArray());

        return $this->success($assignment->load(self::RELATIONS), 'Penugasan kolektor berhasil dibuat.', 201);
    }

    public function update(Request $request, CollectorAssignment $collectorAssignment, AuditService $auditService)
    {
        $data = $this->validateAssignment($request);
        $this->assertNoDuplicateActiveScope($data, $collectorAssignment->id);

        $old = $collectorAssignment->toArray();
        $collectorAssignment->update($data);
        $auditService->log('collector_assignment_updated', 'collector-assignments', 'UPDATE', $collectorAssignment, $old, $collectorAssignment->refresh()->toArray());

        return $this->success($collectorAssignment->load(self::RELATIONS), 'Penugasan kolektor berhasil diperbarui.');
    }

    public function destroy(CollectorAssignment $collectorAssignment, AuditService $auditService)
    {
        $old = $collectorAssignment->toArray();
        $collectorAssignment->update(['is_active' => false, 'status' => CollectorAssignment::STATUS_CANCELLED, 'end_date' => $collectorAssignment->end_date ?? now()->toDateString()]);
        $auditService->log('collector_assignment_revoked', 'collector-assignments', 'DELETE', $collectorAssignment, $old, $collectorAssignment->refresh()->toArray());

        return $this->success(null, 'Penugasan kolektor berhasil dicabut.');
    }

    /**
     * Moves a scope from one collector to another: deactivates the source row (status
     * transferred) and creates a fresh active row for the target, all in one transaction
     * so the duplicate-active-scope guard never has to reason about the old row still
     * being active. Both halves are audit-logged, which is what powers "riwayat
     * penugasan" (CollectorController::assignmentHistory / CollectorProfile detail tab).
     */
    public function reassign(Request $request, CollectorAssignment $collectorAssignment, AuditService $auditService)
    {
        $data = $request->validate([
            'new_collector_id' => ['required', 'exists:users,id', 'different:collector_id'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_if($data['new_collector_id'] == $collectorAssignment->collector_id, 422, 'Kolektor tujuan harus berbeda dari kolektor saat ini.');

        $newAssignment = DB::transaction(function () use ($request, $collectorAssignment, $data, $auditService) {
            $old = $collectorAssignment->toArray();
            $collectorAssignment->update([
                'is_active' => false,
                'status' => CollectorAssignment::STATUS_TRANSFERRED,
                'end_date' => now()->toDateString(),
                'notes' => trim(($collectorAssignment->notes ?? '')."\nDipindahkan ke kolektor lain pada ".now()->toDateTimeString().($data['notes'] ? ": {$data['notes']}" : '')),
            ]);
            $auditService->log('collector_assignment_transferred_out', 'collector-assignments', 'UPDATE', $collectorAssignment, $old, $collectorAssignment->refresh()->toArray());

            $new = CollectorAssignment::query()->create([
                'collector_id' => $data['new_collector_id'],
                'scope_type' => $collectorAssignment->scope_type,
                'cluster_id' => $collectorAssignment->cluster_id,
                'block' => $collectorAssignment->block,
                'unit_id' => $collectorAssignment->unit_id,
                'resident_id' => $collectorAssignment->resident_id,
                'is_active' => true,
                'status' => CollectorAssignment::STATUS_ACTIVE,
                'start_date' => now()->toDateString(),
                // $collectorAssignment->priority can be stale-null in memory if the row was
                // originally created relying on the DB column default rather than an explicit
                // value (create() doesn't re-fetch DB-defaulted columns back into the model).
                'priority' => $collectorAssignment->priority ?: 'normal',
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $request->user()->id,
            ]);
            $auditService->log('collector_assignment_transferred_in', 'collector-assignments', 'CREATE', $new, [], $new->toArray());

            return $new;
        });

        return $this->success($newAssignment->load(self::RELATIONS), 'Penugasan berhasil dipindahkan.', 201);
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'collector_id' => ['required', 'exists:users,id'],
            'scope_type' => ['required', Rule::in(['cluster', 'block', 'unit', 'resident'])],
            'cluster_id' => ['required_if:scope_type,cluster,block', 'nullable', 'exists:clusters,id'],
            'block' => ['required_if:scope_type,block', 'nullable', 'string', 'max:5'],
            'unit_id' => ['required_if:scope_type,unit', 'nullable', 'exists:units,id'],
            'resident_id' => ['required_if:scope_type,resident', 'nullable', 'exists:residents,id'],
            'is_active' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['active', 'completed', 'cancelled', 'transferred'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Only conflicts when both the scope tuple AND the date ranges actually overlap -
     * now that assignments are date-bounded, the same collector can validly cover the
     * same unit again next quarter without tripping a false "duplicate" rejection.
     */
    private function assertNoDuplicateActiveScope(array $data, ?int $ignoreId = null): void
    {
        if (! ($data['is_active'] ?? true)) {
            return;
        }

        $newStart = $data['start_date'] ?? null;
        $newEnd = $data['end_date'] ?? null;

        $exists = CollectorAssignment::query()
            ->where('collector_id', $data['collector_id'])
            ->where('scope_type', $data['scope_type'])
            ->where('cluster_id', $data['cluster_id'] ?? null)
            ->where('block', $data['block'] ?? null)
            ->where('unit_id', $data['unit_id'] ?? null)
            ->where('resident_id', $data['resident_id'] ?? null)
            ->active()
            ->when($ignoreId, fn ($q, $value) => $q->where('id', '!=', $value))
            // Overlap test, each side only constrained when the new range actually bounds
            // it - an open (null) side always overlaps, so no clause is added for it.
            // whereDate() (not a raw string where()) - see CollectorAssignment::scopeCurrentlyEffective()
            // for why a plain string comparison against a `date`-cast column is unsafe.
            ->when($newEnd, fn ($q, $value) => $q->where(fn ($qq) => $qq->whereNull('start_date')->orWhereDate('start_date', '<=', $value)))
            ->when($newStart, fn ($q, $value) => $q->where(fn ($qq) => $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $value)))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'scope_type' => 'Penugasan aktif dengan cakupan dan periode yang tumpang tindih sudah ada untuk kolektor ini.',
            ]);
        }
    }
}
