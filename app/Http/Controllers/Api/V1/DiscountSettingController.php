<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\DiscountSetting;
use App\Services\AuditService;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiscountSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(DiscountSetting::current());
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'maximum_admin_discount' => ['required_without:admin_discount_type', 'numeric', 'min:0', 'max:100'],
            'admin_discount_type' => ['required_without:maximum_admin_discount', Rule::in([DiscountSetting::TYPE_PERCENTAGE, DiscountSetting::TYPE_NOMINAL])],
        ], [
            'admin_discount_type.in' => 'Tipe diskon Admin harus Persentase (%) atau Nominal (Rp).',
            'maximum_admin_discount.max' => 'Batas maksimum diskon Admin tidak boleh lebih dari 100%.',
            'maximum_admin_discount.min' => 'Batas maksimum diskon Admin tidak boleh kurang dari 0%.',
        ]);

        $setting = DiscountSetting::current();
        $old = $setting->toArray();
        $setting->update([...$data, 'updated_by' => $request->user()->id]);
        $auditService->log('discount_settings_updated', 'discount-settings', 'UPDATE', $setting, $old, $setting->refresh()->toArray());

        return $this->success($setting->load('updater'), 'Batas maksimum diskon Admin berhasil disimpan.');
    }

    /**
     * Read-only limit that applies to the *current* user, for the discount forms. Deliberately
     * separate from show() (Super Admin only): an Admin needs to see their own cap while
     * entering a discount, but not the settings record or who last changed it.
     */
    public function limit(Request $request, DiscountService $discountService)
    {
        $limit = $discountService->maximumPercentFor($request->user());

        return $this->success([
            'is_limited' => $limit !== null,
            'maximum_percent' => $limit,
            'discount_type' => $limit !== null ? DiscountSetting::adminDiscountType() : null,
        ]);
    }
}
