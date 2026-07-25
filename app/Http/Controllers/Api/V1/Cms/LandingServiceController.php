<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingService;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingServiceController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingService::query()
            ->with('category')
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingService::query()->max('order') + 1;

        $service = LandingService::query()->create($data);
        $auditService->log('landing_service_created', 'landing-cms', 'CREATE', $service, [], $service->toArray());
        LandingContentCache::forget();

        return $this->success($service, 'Layanan berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingService $landingService, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingService->toArray();
        $landingService->update($data);
        $auditService->log('landing_service_updated', 'landing-cms', 'UPDATE', $landingService, $old, $landingService->toArray());
        LandingContentCache::forget();

        return $this->success($landingService->refresh(), 'Layanan berhasil diperbarui.');
    }

    public function destroy(LandingService $landingService, AuditService $auditService)
    {
        $old = $landingService->toArray();
        $landingService->delete();
        $auditService->log('landing_service_deleted', 'landing-cms', 'DELETE', $landingService, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Layanan berhasil dihapus.');
    }

    public function reorder(Request $request, LandingService $landingService, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingService->moveOrder($data['direction']);
        $auditService->log('landing_service_reordered', 'landing-cms', 'UPDATE', $landingService, [], $landingService->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan layanan berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'icon' => ['nullable', 'string', 'max:60'],
            'image_media_id' => ['nullable', 'exists:media_assets,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:landing_content_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
