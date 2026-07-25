<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\HeroSlide;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeroSlideController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = HeroSlide::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = HeroSlide::query()->max('order') + 1;

        $slide = HeroSlide::query()->create($data);
        $auditService->log('landing_hero_slide_created', 'landing-cms', 'CREATE', $slide, [], $slide->toArray());
        LandingContentCache::forget();

        return $this->success($slide, 'Slide hero berhasil dibuat.', 201);
    }

    public function update(Request $request, HeroSlide $heroSlide, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $heroSlide->toArray();
        $heroSlide->update($data);
        $auditService->log('landing_hero_slide_updated', 'landing-cms', 'UPDATE', $heroSlide, $old, $heroSlide->toArray());
        LandingContentCache::forget();

        return $this->success($heroSlide->refresh(), 'Slide hero berhasil diperbarui.');
    }

    public function destroy(HeroSlide $heroSlide, AuditService $auditService)
    {
        $old = $heroSlide->toArray();
        $heroSlide->delete();
        $auditService->log('landing_hero_slide_deleted', 'landing-cms', 'DELETE', $heroSlide, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Slide hero berhasil dihapus.');
    }

    public function reorder(Request $request, HeroSlide $heroSlide, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $heroSlide->moveOrder($data['direction']);
        $auditService->log('landing_hero_slide_reordered', 'landing-cms', 'UPDATE', $heroSlide, [], $heroSlide->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan slide berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'background_media_id' => ['nullable', 'exists:media_assets,id'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:255'],
            'cta_target' => ['sometimes', Rule::in(['_self', '_blank'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
