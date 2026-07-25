<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingGalleryAlbum;
use App\Models\LandingGalleryItem;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingGalleryItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request, LandingGalleryAlbum $album)
    {
        return $this->success($album->items()->ordered()->get());
    }

    public function store(Request $request, LandingGalleryAlbum $album, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = $album->items()->max('order') + 1;

        $item = $album->items()->create($data);
        $auditService->log('landing_gallery_item_created', 'landing-cms', 'CREATE', $item, [], $item->toArray());
        LandingContentCache::forget();

        return $this->success($item, 'Item galeri berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingGalleryItem $item, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $item->toArray();
        $item->update($data);
        $auditService->log('landing_gallery_item_updated', 'landing-cms', 'UPDATE', $item, $old, $item->toArray());
        LandingContentCache::forget();

        return $this->success($item->refresh(), 'Item galeri berhasil diperbarui.');
    }

    public function destroy(LandingGalleryItem $item, AuditService $auditService)
    {
        $old = $item->toArray();
        $item->delete();
        $auditService->log('landing_gallery_item_deleted', 'landing-cms', 'DELETE', $item, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Item galeri berhasil dihapus.');
    }

    public function reorder(Request $request, LandingGalleryItem $item, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $item->moveOrder($data['direction']);
        $auditService->log('landing_gallery_item_reordered', 'landing-cms', 'UPDATE', $item, [], $item->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan item galeri berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['image', 'video'])],
            'media_id' => ['nullable', 'exists:media_assets,id', 'required_if:type,image'],
            'video_url' => ['nullable', 'string', 'max:500', 'required_if:type,video'],
            'caption' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
