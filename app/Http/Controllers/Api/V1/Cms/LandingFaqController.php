<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingFaq;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingFaqController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingFaq::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('question', 'like', "%{$value}%"))
            ->when($request->query('category_id'), fn ($q, $value) => $q->where('category_id', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingFaq::query()->max('order') + 1;

        $faq = LandingFaq::query()->create($data);
        $auditService->log('landing_faq_created', 'landing-cms', 'CREATE', $faq, [], $faq->toArray());
        LandingContentCache::forget();

        return $this->success($faq, 'FAQ berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingFaq $landingFaq, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingFaq->toArray();
        $landingFaq->update($data);
        $auditService->log('landing_faq_updated', 'landing-cms', 'UPDATE', $landingFaq, $old, $landingFaq->toArray());
        LandingContentCache::forget();

        return $this->success($landingFaq->refresh(), 'FAQ berhasil diperbarui.');
    }

    public function destroy(LandingFaq $landingFaq, AuditService $auditService)
    {
        $old = $landingFaq->toArray();
        $landingFaq->delete();
        $auditService->log('landing_faq_deleted', 'landing-cms', 'DELETE', $landingFaq, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'FAQ berhasil dihapus.');
    }

    public function reorder(Request $request, LandingFaq $landingFaq, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingFaq->moveOrder($data['direction']);
        $auditService->log('landing_faq_reordered', 'landing-cms', 'UPDATE', $landingFaq, [], $landingFaq->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan FAQ berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string'],
            'category_id' => ['nullable', 'exists:landing_content_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
