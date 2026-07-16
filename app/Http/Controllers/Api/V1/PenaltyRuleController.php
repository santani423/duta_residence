<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\PenaltyRule;
use App\Models\PenaltySetting;
use App\Services\AuditService;
use App\Services\PenaltyRuleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenaltyRuleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $rules = PenaltyRule::query()
            ->with(['cluster', 'creator', 'updater'])
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->where('cluster_id', $value))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('cluster_id')
            ->orderBy('minimum_overdue_month')
            ->get();

        return $this->success([
            'rules' => $rules,
            'setting' => PenaltySetting::current(),
        ]);
    }

    public function store(Request $request, PenaltyRuleService $service, AuditService $auditService)
    {
        $data = $this->validateRule($request);
        $service->assertNoOverlap($data);

        $data['created_by'] = $request->user()->id;
        $rule = PenaltyRule::query()->create($data);

        $auditService->log('penalty_rule_created', 'penalty-config', 'CREATE', $rule, [], $rule->toArray());

        return $this->success($rule->fresh(['cluster', 'creator']), 'Aturan denda berhasil dibuat.', 201);
    }

    public function update(Request $request, PenaltyRule $penaltyRule, PenaltyRuleService $service, AuditService $auditService)
    {
        $data = $this->validateRule($request);
        $service->assertNoOverlap($data, $penaltyRule);

        $old = $penaltyRule->toArray();
        $data['updated_by'] = $request->user()->id;
        $penaltyRule->update($data);

        $auditService->log('penalty_rule_updated', 'penalty-config', 'UPDATE', $penaltyRule, $old, $penaltyRule->refresh()->toArray());

        return $this->success($penaltyRule->fresh(['cluster', 'creator', 'updater']), 'Aturan denda berhasil diperbarui.');
    }

    public function destroy(Request $request, PenaltyRule $penaltyRule, AuditService $auditService)
    {
        $old = $penaltyRule->toArray();
        $penaltyRule->update(['updated_by' => $request->user()->id]);
        $penaltyRule->delete();

        $auditService->log('penalty_rule_deleted', 'penalty-config', 'DELETE', $penaltyRule, $old, []);

        return $this->success(null, 'Aturan denda berhasil dinonaktifkan.');
    }

    public function history(Request $request)
    {
        $query = AuditLog::query()
            ->where('module', 'penalty-config')
            ->when($request->query('entity_id'), fn ($q, $value) => $q->where('entity_id', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function showSetting()
    {
        return $this->success(PenaltySetting::current());
    }

    public function updateSetting(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'allocation_order' => ['required', Rule::in([PenaltySetting::ALLOCATION_PENALTY_FIRST, PenaltySetting::ALLOCATION_PRINCIPAL_FIRST])],
        ]);

        $setting = PenaltySetting::current();
        $old = $setting->toArray();
        $setting->update([...$data, 'updated_by' => $request->user()->id]);

        $auditService->log('penalty_setting_updated', 'penalty-config', 'SETTING_UPDATE', $setting, $old, $setting->refresh()->toArray());

        return $this->success($setting, 'Pengaturan denda berhasil disimpan.');
    }

    private function validateRule(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'cluster_id' => ['nullable', 'exists:clusters,id'],
            'minimum_overdue_month' => ['required', 'integer', 'min:0'],
            'maximum_overdue_month' => ['nullable', 'integer', 'gte:minimum_overdue_month'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
        ]);
    }
}
