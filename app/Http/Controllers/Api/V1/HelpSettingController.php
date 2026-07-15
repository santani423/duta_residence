<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\HelpSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpSettingController extends Controller
{
    use ApiResponse;

    /**
     * Seluruh baris pengaturan dikirim sekali ke frontend (jumlahnya kecil) supaya
     * setiap ikon info bisa resolve visibilitasnya sendiri tanpa panggilan API per ikon.
     */
    public function index()
    {
        HelpSetting::query()->firstOrCreate(['scope_type' => 'global', 'scope_key' => null], ['is_enabled' => true]);

        return $this->success([
            'settings' => HelpSetting::allCached()->values(),
            'app_version' => config('grandduta.app_version'),
            'support' => config('grandduta.support'),
        ]);
    }

    public function upsert(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.scope_type' => ['required', Rule::in(['global', 'role', 'module', 'page', 'component'])],
            'settings.*.scope_key' => ['nullable', 'string', 'max:150'],
            'settings.*.is_enabled' => ['required', 'boolean'],
        ]);

        foreach ($data['settings'] as $row) {
            if ($row['scope_type'] === 'global') {
                $row['scope_key'] = null;
            }

            $setting = HelpSetting::query()->updateOrCreate(
                ['scope_type' => $row['scope_type'], 'scope_key' => $row['scope_key'] ?? null],
                ['is_enabled' => $row['is_enabled'], 'updated_by' => $request->user()->id]
            );
            $auditService->log('help_setting_updated', 'help-settings', 'UPDATE', $setting, [], $setting->toArray());
        }

        HelpSetting::forgetCache();

        return $this->success(HelpSetting::allCached()->values(), 'Pengaturan bantuan berhasil disimpan.');
    }

    public function destroy(HelpSetting $setting)
    {
        abort_if($setting->scope_type === 'global', 422, 'Pengaturan global tidak dapat dihapus.');
        $setting->delete();
        HelpSetting::forgetCache();

        return $this->success(null, 'Override pengaturan berhasil dihapus.');
    }
}
