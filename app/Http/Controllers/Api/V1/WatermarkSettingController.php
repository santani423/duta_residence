<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\WatermarkSetting;
use App\Services\AuditService;
use App\Services\MediaProcessingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WatermarkSettingController extends Controller
{
    use ApiResponse;

    /**
     * Read on every authenticated role (not gated behind
     * watermark-settings.manage) - the overlay that renders the watermark
     * across the whole app shell needs this for every logged-in user, only
     * changing it is restricted to Super Admin.
     */
    public function show()
    {
        return $this->success(WatermarkSetting::current()->load('media'));
    }

    public function update(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'type' => ['required', Rule::in(['text', 'image'])],
            'text_content' => ['required_if:type,text', 'nullable', 'string', 'max:100'],
            'media_id' => ['required_if:type,image', 'nullable', 'exists:media_assets,id'],
            'opacity' => ['required', 'integer', 'min:10', 'max:100'],
            'mode' => ['required', Rule::in(['single', 'multiple'])],
            'size' => ['required', 'integer', 'min:40', 'max:600'],
            'position' => ['required_if:mode,single', Rule::in([
                'top-left', 'top-center', 'top-right',
                'center',
                'bottom-left', 'bottom-center', 'bottom-right',
            ])],
            'spacing' => ['required_if:mode,multiple', 'integer', 'min:20', 'max:500'],
        ]);

        $setting = WatermarkSetting::current();
        $old = $setting->toArray();
        $setting->update([...$data, 'updated_by' => $request->user()->id]);
        $auditService->log('watermark_settings_updated', 'watermark-settings', 'UPDATE', $setting, $old, $setting->toArray());

        return $this->success($setting->refresh()->load('media'), 'Pengaturan watermark berhasil disimpan.');
    }

    public function uploadLogo(Request $request, MediaProcessingService $mediaProcessingService)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $media = $mediaProcessingService->storeUploadedFile($data['file'], $request->user()->id, 'Watermark logo');
        $media->update(['entity_type' => 'watermark_settings']);

        return $this->success($media, 'Logo watermark berhasil diunggah.', 201);
    }
}
