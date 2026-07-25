<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingFeature;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingFeatureController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingFeature::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingFeature::query()->max('order') + 1;

        $feature = LandingFeature::query()->create($data);
        $auditService->log('landing_feature_created', 'landing-cms', 'CREATE', $feature, [], $feature->toArray());
        LandingContentCache::forget();

        return $this->success($feature, 'Fitur berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingFeature $landingFeature, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingFeature->toArray();
        $landingFeature->update($data);
        $auditService->log('landing_feature_updated', 'landing-cms', 'UPDATE', $landingFeature, $old, $landingFeature->toArray());
        LandingContentCache::forget();

        return $this->success($landingFeature->refresh(), 'Fitur berhasil diperbarui.');
    }

    public function destroy(LandingFeature $landingFeature, AuditService $auditService)
    {
        $old = $landingFeature->toArray();
        $landingFeature->delete();
        $auditService->log('landing_feature_deleted', 'landing-cms', 'DELETE', $landingFeature, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Fitur berhasil dihapus.');
    }

    public function reorder(Request $request, LandingFeature $landingFeature, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingFeature->moveOrder($data['direction']);
        $auditService->log('landing_feature_reordered', 'landing-cms', 'UPDATE', $landingFeature, [], $landingFeature->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan fitur berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'icon' => ['nullable', 'string', 'max:60'],
            'image_media_id' => ['nullable', 'exists:media_assets,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'badge' => ['nullable', 'string', 'max:60'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
