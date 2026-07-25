<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingArticle;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingArticleController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingArticle::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingArticle::query()->max('order') + 1;
        $data['author_id'] = $request->user()->id;

        $article = LandingArticle::query()->create($data);
        $auditService->log('landing_article_created', 'landing-cms', 'CREATE', $article, [], $article->toArray());
        LandingContentCache::forget();

        return $this->success($article, 'Artikel berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingArticle $landingArticle, AuditService $auditService)
    {
        $data = $this->validated($request, $landingArticle);
        $old = $landingArticle->toArray();
        $landingArticle->update($data);
        $auditService->log('landing_article_updated', 'landing-cms', 'UPDATE', $landingArticle, $old, $landingArticle->toArray());
        LandingContentCache::forget();

        return $this->success($landingArticle->refresh(), 'Artikel berhasil diperbarui.');
    }

    public function destroy(LandingArticle $landingArticle, AuditService $auditService)
    {
        $old = $landingArticle->toArray();
        $landingArticle->delete();
        $auditService->log('landing_article_deleted', 'landing-cms', 'DELETE', $landingArticle, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Artikel berhasil dihapus.');
    }

    public function reorder(Request $request, LandingArticle $landingArticle, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingArticle->moveOrder($data['direction']);
        $auditService->log('landing_article_reordered', 'landing-cms', 'UPDATE', $landingArticle, [], $landingArticle->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan artikel berhasil diperbarui.');
    }

    private function validated(Request $request, ?LandingArticle $article = null): array
    {
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:landing_content_categories,id'],
            'title' => ['required', 'string', 'max:250'],
            'slug' => ['required', 'string', 'max:270'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'featured_image_media_id' => ['nullable', 'exists:media_assets,id'],
            'thumbnail_media_id' => ['nullable', 'exists:media_assets,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'published'])],
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slugTaken = LandingArticle::query()
            ->where('slug', $data['slug'])
            ->when($article, fn ($q) => $q->where('id', '!=', $article->id))
            ->exists();

        if ($slugTaken) {
            throw ValidationException::withMessages(['slug' => ['Slug sudah digunakan.']]);
        }

        return $data;
    }
}
