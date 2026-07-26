<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SupervisorAssignment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupervisorAssignmentController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['supervisor', 'cluster', 'assignedBy'];

    public function index(Request $request)
    {
        $query = SupervisorAssignment::query()
            ->with(self::RELATIONS)
            ->when($request->query('supervisor_id'), fn ($q, $value) => $q->where('supervisor_id', $value))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
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
        $data['status'] = $data['status'] ?? SupervisorAssignment::STATUS_ACTIVE;

        $assignment = SupervisorAssignment::query()->create($data);
        $auditService->log('supervisor_assignment_created', 'supervisor-assignments', 'CREATE', $assignment, [], $assignment->toArray());

        return $this->success($assignment->load(self::RELATIONS), 'Penugasan wilayah supervisor berhasil dibuat.', 201);
    }

    public function update(Request $request, SupervisorAssignment $supervisorAssignment, AuditService $auditService)
    {
        $data = $this->validateAssignment($request);
        $this->assertNoDuplicateActiveScope($data, $supervisorAssignment->id);

        $old = $supervisorAssignment->toArray();
        $supervisorAssignment->update($data);
        $auditService->log('supervisor_assignment_updated', 'supervisor-assignments', 'UPDATE', $supervisorAssignment, $old, $supervisorAssignment->refresh()->toArray());

        return $this->success($supervisorAssignment->load(self::RELATIONS), 'Penugasan wilayah supervisor berhasil diperbarui.');
    }

    public function destroy(SupervisorAssignment $supervisorAssignment, AuditService $auditService)
    {
        $old = $supervisorAssignment->toArray();
        $supervisorAssignment->update([
            'is_active' => false,
            'status' => SupervisorAssignment::STATUS_CANCELLED,
            'end_date' => $supervisorAssignment->end_date ?? now()->toDateString(),
        ]);
        $auditService->log('supervisor_assignment_revoked', 'supervisor-assignments', 'DELETE', $supervisorAssignment, $old, $supervisorAssignment->refresh()->toArray());

        return $this->success(null, 'Penugasan wilayah supervisor berhasil dicabut.');
    }

    /**
     * Same transfer pattern as CollectorAssignmentController::reassign(): deactivate the
     * source row (status=transferred) and create a fresh active row for the target
     * supervisor, both in one transaction, both audit-logged.
     */
    public function reassign(Request $request, SupervisorAssignment $supervisorAssignment, AuditService $auditService)
    {
        $data = $request->validate([
            'new_supervisor_id' => ['required', 'exists:users,id', 'different:supervisor_id'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_if($data['new_supervisor_id'] == $supervisorAssignment->supervisor_id, 422, 'Supervisor tujuan harus berbeda dari supervisor saat ini.');

        $newAssignment = DB::transaction(function () use ($request, $supervisorAssignment, $data, $auditService) {
            $old = $supervisorAssignment->toArray();
            $supervisorAssignment->update([
                'is_active' => false,
                'status' => SupervisorAssignment::STATUS_TRANSFERRED,
                'end_date' => now()->toDateString(),
                'notes' => trim(($supervisorAssignment->notes ?? '')."\nDipindahkan ke supervisor lain pada ".now()->toDateTimeString().(($data['notes'] ?? null) ? ": {$data['notes']}" : '')),
            ]);
            $auditService->log('supervisor_assignment_transferred_out', 'supervisor-assignments', 'UPDATE', $supervisorAssignment, $old, $supervisorAssignment->refresh()->toArray());

            $new = SupervisorAssignment::query()->create([
                'supervisor_id' => $data['new_supervisor_id'],
                'cluster_id' => $supervisorAssignment->cluster_id,
                'is_active' => true,
                'status' => SupervisorAssignment::STATUS_ACTIVE,
                'start_date' => now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'assigned_by' => $request->user()->id,
            ]);
            $auditService->log('supervisor_assignment_transferred_in', 'supervisor-assignments', 'CREATE', $new, [], $new->toArray());

            return $new;
        });

        return $this->success($newAssignment->load(self::RELATIONS), 'Penugasan wilayah berhasil dipindahkan.', 201);
    }

    private function validateAssignment(Request $request): array
    {
        return $request->validate([
            'supervisor_id' => ['required', 'exists:users,id'],
            'cluster_id' => ['required', 'exists:clusters,id'],
            'is_active' => ['nullable', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['active', 'completed', 'cancelled', 'transferred'])],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function assertNoDuplicateActiveScope(array $data, ?int $ignoreId = null): void
    {
        if (! ($data['is_active'] ?? true)) {
            return;
        }

        $newStart = $data['start_date'] ?? null;
        $newEnd = $data['end_date'] ?? null;

        $exists = SupervisorAssignment::query()
            ->where('supervisor_id', $data['supervisor_id'])
            ->where('cluster_id', $data['cluster_id'])
            ->active()
            ->when($ignoreId, fn ($q, $value) => $q->where('id', '!=', $value))
            ->when($newEnd, fn ($q, $value) => $q->where(fn ($qq) => $qq->whereNull('start_date')->orWhereDate('start_date', '<=', $value)))
            ->when($newStart, fn ($q, $value) => $q->where(fn ($qq) => $qq->whereNull('end_date')->orWhereDate('end_date', '>=', $value)))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'cluster_id' => 'Penugasan aktif dengan cluster dan periode yang tumpang tindih sudah ada untuk supervisor ini.',
            ]);
        }
    }
}
