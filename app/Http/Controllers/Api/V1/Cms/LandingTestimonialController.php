<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingTestimonial;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingTestimonialController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingTestimonial::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingTestimonial::query()->max('order') + 1;

        $testimonial = LandingTestimonial::query()->create($data);
        $auditService->log('landing_testimonial_created', 'landing-cms', 'CREATE', $testimonial, [], $testimonial->toArray());
        LandingContentCache::forget();

        return $this->success($testimonial, 'Testimoni berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingTestimonial $landingTestimonial, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingTestimonial->toArray();
        $landingTestimonial->update($data);
        $auditService->log('landing_testimonial_updated', 'landing-cms', 'UPDATE', $landingTestimonial, $old, $landingTestimonial->toArray());
        LandingContentCache::forget();

        return $this->success($landingTestimonial->refresh(), 'Testimoni berhasil diperbarui.');
    }

    public function destroy(LandingTestimonial $landingTestimonial, AuditService $auditService)
    {
        $old = $landingTestimonial->toArray();
        $landingTestimonial->delete();
        $auditService->log('landing_testimonial_deleted', 'landing-cms', 'DELETE', $landingTestimonial, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Testimoni berhasil dihapus.');
    }

    public function reorder(Request $request, LandingTestimonial $landingTestimonial, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingTestimonial->moveOrder($data['direction']);
        $auditService->log('landing_testimonial_reordered', 'landing-cms', 'UPDATE', $landingTestimonial, [], $landingTestimonial->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan testimoni berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'photo_media_id' => ['nullable', 'exists:media_assets,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'content' => ['required', 'string'],
            'rating' => ['sometimes', 'integer', 'between:1,5'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
