<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingContentCategory;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingContentCategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingContentCategory::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->when($request->query('group'), fn ($q, $value) => $q->where('group', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingContentCategory::query()->max('order') + 1;

        $category = LandingContentCategory::query()->create($data);
        $auditService->log('landing_content_category_created', 'landing-cms', 'CREATE', $category, [], $category->toArray());
        LandingContentCache::forget();

        return $this->success($category, 'Kategori berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingContentCategory $landingContentCategory, AuditService $auditService)
    {
        $data = $this->validated($request, $landingContentCategory);
        $old = $landingContentCategory->toArray();
        $landingContentCategory->update($data);
        $auditService->log('landing_content_category_updated', 'landing-cms', 'UPDATE', $landingContentCategory, $old, $landingContentCategory->toArray());
        LandingContentCache::forget();

        return $this->success($landingContentCategory->refresh(), 'Kategori berhasil diperbarui.');
    }

    public function destroy(LandingContentCategory $landingContentCategory, AuditService $auditService)
    {
        $old = $landingContentCategory->toArray();
        $landingContentCategory->delete();
        $auditService->log('landing_content_category_deleted', 'landing-cms', 'DELETE', $landingContentCategory, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Kategori berhasil dihapus.');
    }

    public function reorder(Request $request, LandingContentCategory $landingContentCategory, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingContentCategory->moveOrder($data['direction']);
        $auditService->log('landing_content_category_reordered', 'landing-cms', 'UPDATE', $landingContentCategory, [], $landingContentCategory->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan kategori berhasil diperbarui.');
    }

    private function validated(Request $request, ?LandingContentCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required', 'string', 'max:120',
                Rule::unique('landing_content_categories', 'slug')
                    ->where(fn ($q) => $q->where('group', $request->input('group')))
                    ->ignore($category?->id),
            ],
            'group' => ['required', Rule::in(['service', 'news', 'gallery', 'faq'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
