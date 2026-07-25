<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingAboutSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;

class LandingAboutSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(LandingAboutSetting::current()->load('image'));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'image_media_id' => ['nullable', 'exists:media_assets,id'],
            'pillars' => ['nullable', 'array'],
            'pillars.*.icon' => ['nullable', 'string', 'max:60'],
            'pillars.*.title' => ['required_with:pillars', 'string', 'max:100'],
            'pillars.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $setting = LandingAboutSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('landing_about_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh()->load('image'), 'Pengaturan tentang kami berhasil disimpan.');
    }
}
