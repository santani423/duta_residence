<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingSocialLink;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingSocialLinkController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingSocialLink::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('platform', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingSocialLink::query()->max('order') + 1;

        $link = LandingSocialLink::query()->create($data);
        $auditService->log('landing_social_link_created', 'landing-cms', 'CREATE', $link, [], $link->toArray());
        LandingContentCache::forget();

        return $this->success($link, 'Tautan sosial berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingSocialLink $landingSocialLink, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingSocialLink->toArray();
        $landingSocialLink->update($data);
        $auditService->log('landing_social_link_updated', 'landing-cms', 'UPDATE', $landingSocialLink, $old, $landingSocialLink->toArray());
        LandingContentCache::forget();

        return $this->success($landingSocialLink->refresh(), 'Tautan sosial berhasil diperbarui.');
    }

    public function destroy(LandingSocialLink $landingSocialLink, AuditService $auditService)
    {
        $old = $landingSocialLink->toArray();
        $landingSocialLink->delete();
        $auditService->log('landing_social_link_deleted', 'landing-cms', 'DELETE', $landingSocialLink, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Tautan sosial berhasil dihapus.');
    }

    public function reorder(Request $request, LandingSocialLink $landingSocialLink, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingSocialLink->moveOrder($data['direction']);
        $auditService->log('landing_social_link_reordered', 'landing-cms', 'UPDATE', $landingSocialLink, [], $landingSocialLink->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan tautan sosial berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'platform' => ['required', Rule::in(['facebook', 'instagram', 'tiktok', 'youtube', 'linkedin', 'twitter_x', 'telegram', 'custom'])],
            'url' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
