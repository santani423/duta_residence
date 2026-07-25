<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingContactSetting;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;

class LandingContactSettingController extends Controller
{
    use ApiResponse;

    public function show()
    {
        return $this->success(LandingContactSetting::current());
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:60'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:150'],
            'maps_embed_url' => ['nullable', 'string', 'max:500'],
            'maps_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'maps_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.label' => ['required_with:business_hours', 'string', 'max:100'],
            'business_hours.*.value' => ['required_with:business_hours', 'string', 'max:150'],
        ]);

        $setting = LandingContactSetting::current();
        $old = $setting->toArray();
        $setting->update($data);
        $auditService->log('landing_contact_settings_updated', 'landing-cms', 'UPDATE', $setting, $old, $setting->toArray());
        LandingContentCache::forget();

        return $this->success($setting->refresh(), 'Pengaturan kontak berhasil disimpan.');
    }
}
