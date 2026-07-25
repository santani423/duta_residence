<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingFooterSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;

class LandingFooterSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(LandingFooterSetting::current()->load('logo'));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'logo_media_id' => ['nullable', 'exists:media_assets,id'],
            'description' => ['nullable', 'string'],
            'copyright_text' => ['nullable', 'string', 'max:255'],
            'show_social_links' => ['required', 'boolean'],
            'show_quick_links' => ['required', 'boolean'],
        ]);

        $setting = LandingFooterSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('landing_footer_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh()->load('logo'), 'Pengaturan footer berhasil disimpan.');
    }
}
