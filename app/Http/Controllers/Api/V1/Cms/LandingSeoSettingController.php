<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingSeoSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingSeoSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(LandingSeoSetting::current()->load(['ogImage', 'favicon']));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_media_id' => ['nullable', 'exists:media_assets,id'],
            'twitter_card_type' => ['required', Rule::in(['summary', 'summary_large_image'])],
            'favicon_media_id' => ['nullable', 'exists:media_assets,id'],
            'structured_data' => ['nullable', 'string', 'json'],
        ]);

        $setting = LandingSeoSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('landing_seo_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh()->load(['ogImage', 'favicon']), 'Pengaturan SEO berhasil disimpan.');
    }
}
