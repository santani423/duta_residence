<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorTarget;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorTargetController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = CollectorTarget::query()
            ->with('collector')
            ->when($request->query('collector_id'), fn ($q, $value) => $q->where('collector_id', $value))
            ->when($request->query('period_type'), fn ($q, $value) => $q->where('period_type', $value));

        return $this->paginated($query->latest('period_start')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateTarget($request);
        $data['created_by'] = $request->user()->id;

        $target = CollectorTarget::query()->create($data);
        $auditService->log('collector_target_created', 'collector-targets', 'CREATE', $target, [], $target->toArray());

        return $this->success($target->load('collector'), 'Target kolektor berhasil dibuat.', 201);
    }

    public function update(Request $request, CollectorTarget $collectorTarget, AuditService $auditService)
    {
        $data = $this->validateTarget($request, $collectorTarget);
        $old = $collectorTarget->toArray();
        $collectorTarget->update($data);
        $auditService->log('collector_target_updated', 'collector-targets', 'UPDATE', $collectorTarget, $old, $collectorTarget->refresh()->toArray());

        return $this->success($collectorTarget->load('collector'), 'Target kolektor berhasil diperbarui.');
    }

    public function destroy(CollectorTarget $collectorTarget, AuditService $auditService)
    {
        $old = $collectorTarget->toArray();
        $collectorTarget->delete();
        $auditService->log('collector_target_deleted', 'collector-targets', 'DELETE', $collectorTarget, $old, []);

        return $this->success(null, 'Target kolektor berhasil dihapus.');
    }

    private function validateTarget(Request $request, ?CollectorTarget $target = null): array
    {
        return $request->validate([
            'collector_id' => ['required', 'exists:users,id'],
            'period_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'period_start' => [
                'required', 'date',
                Rule::unique('collector_targets', 'period_start')
                    ->where(fn ($q) => $q->where('collector_id', $request->input('collector_id'))->where('period_type', $request->input('period_type')))
                    ->ignore($target?->id),
            ],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'target_visit_count' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
