<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingPartner;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingPartnerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingPartner::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingPartner::query()->max('order') + 1;

        $partner = LandingPartner::query()->create($data);
        $auditService->log('landing_partner_created', 'landing-cms', 'CREATE', $partner, [], $partner->toArray());
        LandingContentCache::forget();

        return $this->success($partner, 'Mitra berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingPartner $landingPartner, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingPartner->toArray();
        $landingPartner->update($data);
        $auditService->log('landing_partner_updated', 'landing-cms', 'UPDATE', $landingPartner, $old, $landingPartner->toArray());
        LandingContentCache::forget();

        return $this->success($landingPartner->refresh(), 'Mitra berhasil diperbarui.');
    }

    public function destroy(LandingPartner $landingPartner, AuditService $auditService)
    {
        $old = $landingPartner->toArray();
        $landingPartner->delete();
        $auditService->log('landing_partner_deleted', 'landing-cms', 'DELETE', $landingPartner, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Mitra berhasil dihapus.');
    }

    public function reorder(Request $request, LandingPartner $landingPartner, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingPartner->moveOrder($data['direction']);
        $auditService->log('landing_partner_reordered', 'landing-cms', 'UPDATE', $landingPartner, [], $landingPartner->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan mitra berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'logo_media_id' => ['nullable', 'exists:media_assets,id'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
