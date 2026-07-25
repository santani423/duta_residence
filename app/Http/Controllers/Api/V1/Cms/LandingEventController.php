<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingEvent;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingEventController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingEvent::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('title', 'like', "%{$value}%"))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingEvent::query()->max('order') + 1;

        $event = LandingEvent::query()->create($data);
        $auditService->log('landing_event_created', 'landing-cms', 'CREATE', $event, [], $event->toArray());
        LandingContentCache::forget();

        return $this->success($event, 'Acara berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingEvent $landingEvent, AuditService $auditService)
    {
        $data = $this->validated($request, $landingEvent);
        $old = $landingEvent->toArray();
        $landingEvent->update($data);
        $auditService->log('landing_event_updated', 'landing-cms', 'UPDATE', $landingEvent, $old, $landingEvent->toArray());
        LandingContentCache::forget();

        return $this->success($landingEvent->refresh(), 'Acara berhasil diperbarui.');
    }

    public function destroy(LandingEvent $landingEvent, AuditService $auditService)
    {
        $old = $landingEvent->toArray();
        $landingEvent->delete();
        $auditService->log('landing_event_deleted', 'landing-cms', 'DELETE', $landingEvent, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Acara berhasil dihapus.');
    }

    public function reorder(Request $request, LandingEvent $landingEvent, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingEvent->moveOrder($data['direction']);
        $auditService->log('landing_event_reordered', 'landing-cms', 'UPDATE', $landingEvent, [], $landingEvent->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan acara berhasil diperbarui.');
    }

    private function validated(Request $request, ?LandingEvent $event = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:220'],
            'banner_media_id' => ['nullable', 'exists:media_assets,id'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'registration_url' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $slugTaken = LandingEvent::query()
            ->where('slug', $data['slug'])
            ->when($event, fn ($q) => $q->where('id', '!=', $event->id))
            ->exists();

        if ($slugTaken) {
            throw ValidationException::withMessages(['slug' => ['Slug sudah digunakan.']]);
        }

        return $data;
    }
}
