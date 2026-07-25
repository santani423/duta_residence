<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\SiteSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(SiteSetting::current()->load('logo'));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'logo_media_id' => ['nullable', 'exists:media_assets,id'],
            'default_theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'primary_color' => ['required', 'string', 'max:20'],
            'secondary_color' => ['required', 'string', 'max:20'],
            'default_language' => ['required', 'string', 'max:10'],
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'analytics_script' => ['nullable', 'string'],
            'pixel_script' => ['nullable', 'string'],
            'custom_css' => ['nullable', 'string'],
            'custom_js' => ['nullable', 'string'],
        ]);

        $setting = SiteSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('site_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh()->load('logo'), 'Pengaturan umum berhasil disimpan.');
    }
}
