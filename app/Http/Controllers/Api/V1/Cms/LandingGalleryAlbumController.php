<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingGalleryAlbum;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingGalleryAlbumController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingGalleryAlbum::query()
            ->withCount('items')
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingGalleryAlbum::query()->max('order') + 1;

        $album = LandingGalleryAlbum::query()->create($data);
        $auditService->log('landing_gallery_album_created', 'landing-cms', 'CREATE', $album, [], $album->toArray());
        LandingContentCache::forget();

        return $this->success($album, 'Album galeri berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingGalleryAlbum $landingGalleryAlbum, AuditService $auditService)
    {
        $data = $this->validated($request, $landingGalleryAlbum);
        $old = $landingGalleryAlbum->toArray();
        $landingGalleryAlbum->update($data);
        $auditService->log('landing_gallery_album_updated', 'landing-cms', 'UPDATE', $landingGalleryAlbum, $old, $landingGalleryAlbum->toArray());
        LandingContentCache::forget();

        return $this->success($landingGalleryAlbum->refresh(), 'Album galeri berhasil diperbarui.');
    }

    public function destroy(LandingGalleryAlbum $landingGalleryAlbum, AuditService $auditService)
    {
        $old = $landingGalleryAlbum->toArray();
        $landingGalleryAlbum->delete();
        $auditService->log('landing_gallery_album_deleted', 'landing-cms', 'DELETE', $landingGalleryAlbum, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Album galeri berhasil dihapus.');
    }

    public function reorder(Request $request, LandingGalleryAlbum $landingGalleryAlbum, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingGalleryAlbum->moveOrder($data['direction']);
        $auditService->log('landing_gallery_album_reordered', 'landing-cms', 'UPDATE', $landingGalleryAlbum, [], $landingGalleryAlbum->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan album galeri berhasil diperbarui.');
    }

    private function validated(Request $request, ?LandingGalleryAlbum $album = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:220'],
            'cover_media_id' => ['nullable', 'exists:media_assets,id'],
            'category_id' => ['nullable', 'exists:landing_content_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slugTaken = LandingGalleryAlbum::query()
            ->where('slug', $data['slug'])
            ->when($album, fn ($q) => $q->where('id', '!=', $album->id))
            ->exists();

        if ($slugTaken) {
            throw ValidationException::withMessages(['slug' => ['Slug sudah digunakan.']]);
        }

        return $data;
    }
}
