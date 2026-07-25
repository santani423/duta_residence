<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MediaAsset;
use App\Services\AuditService;
use App\Services\MediaProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    use ApiResponse;

    /**
     * Every *_media_id foreign key in the schema, keyed by table. Used by
     * destroy() to refuse deleting an asset that's still referenced anywhere,
     * rather than silently orphaning references via nullOnDelete().
     */
    private function usageMap(): array
    {
        return [
            'landing_header_settings' => ['logo_media_id'],
            'landing_about_settings' => ['image_media_id'],
            'landing_footer_settings' => ['logo_media_id'],
            'landing_seo_settings' => ['og_image_media_id', 'favicon_media_id'],
            'site_settings' => ['logo_media_id'],
            'hero_slides' => ['background_media_id'],
            'landing_services' => ['image_media_id'],
            'landing_features' => ['image_media_id'],
            'landing_testimonials' => ['photo_media_id'],
            'landing_partners' => ['logo_media_id'],
            'landing_gallery_albums' => ['cover_media_id'],
            'landing_gallery_items' => ['media_id'],
            'landing_events' => ['banner_media_id'],
            'landing_articles' => ['featured_image_media_id', 'thumbnail_media_id'],
        ];
    }

    public function index(Request $request)
    {
        $query = MediaAsset::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('alt_text', 'like', "%{$value}%"))
            ->when($request->query('mime_type'), fn ($q, $value) => $q->where('mime_type', 'like', "{$value}%"))
            ->latest();

        return $this->paginated($query->paginate($request->integer('per_page', 24)));
    }

    public function store(Request $request, AuditService $auditService, MediaProcessingService $mediaProcessingService)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'entity_type' => ['nullable', 'string', 'max:100'],
            'entity_id' => ['nullable', 'string', 'max:50'],
        ]);

        $media = $mediaProcessingService->storeUploadedFile($data['file'], $request->user()->id, $data['alt_text'] ?? null);

        if (! empty($data['entity_type']) || ! empty($data['entity_id'])) {
            $media->update([
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id' => $data['entity_id'] ?? null,
            ]);
        }

        $auditService->log('landing_media_uploaded', 'landing-cms', 'CREATE', $media, [], $media->toArray());

        return $this->success($media, 'Media berhasil diunggah.', 201);
    }

    public function destroy(MediaAsset $media, AuditService $auditService)
    {
        $usages = [];
        foreach ($this->usageMap() as $table => $columns) {
            foreach ($columns as $column) {
                $count = DB::table($table)->where($column, $media->id)->count();
                if ($count > 0) {
                    $usages[] = "{$table}.{$column} ({$count})";
                }
            }
        }

        if ($usages) {
            return $this->error('Media tidak dapat dihapus karena masih digunakan di: '.implode(', ', $usages), 422);
        }

        $paths = array_filter([$media->path, ...array_values($media->variants ?? [])]);
        Storage::disk($media->disk)->delete($paths);

        $old = $media->toArray();
        $media->delete();
        $auditService->log('landing_media_deleted', 'landing-cms', 'DELETE', $media, $old, []);

        return $this->success(null, 'Media berhasil dihapus.');
    }
}
