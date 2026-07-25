<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingHeaderSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;

class LandingHeaderSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(LandingHeaderSetting::current()->load('logo'));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'logo_media_id' => ['nullable', 'exists:media_assets,id'],
            'site_name' => ['required', 'string', 'max:150'],
            'sticky_enabled' => ['required', 'boolean'],
            'show_login_button' => ['required', 'boolean'],
            'login_button_text' => ['required', 'string', 'max:60'],
            'cta_button_text' => ['nullable', 'string', 'max:60'],
            'cta_button_url' => ['nullable', 'string', 'max:255'],
            'cta_button_enabled' => ['required', 'boolean'],
        ]);

        $setting = LandingHeaderSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('landing_header_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh()->load('logo'), 'Pengaturan header berhasil disimpan.');
    }
}
