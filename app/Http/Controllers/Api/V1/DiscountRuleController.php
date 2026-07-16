<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DiscountRule;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiscountRuleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $rules = DiscountRule::query()
            ->with(['creator', 'updater'])
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return $this->success($rules);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateRule($request);
        $data['created_by'] = $request->user()->id;
        $rule = DiscountRule::query()->create($data);

        $auditService->log('discount_rule_created', 'discount-config', 'CREATE', $rule, [], $rule->toArray());

        return $this->success($rule->fresh(['creator']), 'Aturan diskon berhasil dibuat.', 201);
    }

    public function update(Request $request, DiscountRule $discountRule, AuditService $auditService)
    {
        $data = $this->validateRule($request);
        $old = $discountRule->toArray();
        $data['updated_by'] = $request->user()->id;
        $discountRule->update($data);

        $auditService->log('discount_rule_updated', 'discount-config', 'UPDATE', $discountRule, $old, $discountRule->refresh()->toArray());

        return $this->success($discountRule->fresh(['creator', 'updater']), 'Aturan diskon berhasil diperbarui.');
    }

    public function destroy(Request $request, DiscountRule $discountRule, AuditService $auditService)
    {
        $old = $discountRule->toArray();
        $discountRule->update(['updated_by' => $request->user()->id]);
        $discountRule->delete();

        $auditService->log('discount_rule_deleted', 'discount-config', 'DELETE', $discountRule, $old, []);

        return $this->success(null, 'Aturan diskon berhasil dinonaktifkan.');
    }

    private function validateRule(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in([DiscountRule::TYPE_FIXED, DiscountRule::TYPE_PERCENTAGE])],
            'value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($data['type'] === DiscountRule::TYPE_PERCENTAGE && $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => ['Persentase diskon tidak boleh lebih dari 100.'],
            ]);
        }

        return $data;
    }
}
