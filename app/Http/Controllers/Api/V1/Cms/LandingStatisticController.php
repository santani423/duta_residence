<?php

namespace App\Http\Controllers\Api\V1\Cms;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\LandingStatistic;
use App\Services\AuditService;
use App\Support\LandingContentCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingStatisticController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LandingStatistic::query()
            ->when($request->query('search'), fn ($q, $value) => $q->where('label', 'like', "%{$value}%"))
            ->ordered();

        return $this->paginated($query->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validated($request);
        $data['order'] = LandingStatistic::query()->max('order') + 1;

        $statistic = LandingStatistic::query()->create($data);
        $auditService->log('landing_statistic_created', 'landing-cms', 'CREATE', $statistic, [], $statistic->toArray());
        LandingContentCache::forget();

        return $this->success($statistic, 'Statistik berhasil dibuat.', 201);
    }

    public function update(Request $request, LandingStatistic $landingStatistic, AuditService $auditService)
    {
        $data = $this->validated($request);
        $old = $landingStatistic->toArray();
        $landingStatistic->update($data);
        $auditService->log('landing_statistic_updated', 'landing-cms', 'UPDATE', $landingStatistic, $old, $landingStatistic->toArray());
        LandingContentCache::forget();

        return $this->success($landingStatistic->refresh(), 'Statistik berhasil diperbarui.');
    }

    public function destroy(LandingStatistic $landingStatistic, AuditService $auditService)
    {
        $old = $landingStatistic->toArray();
        $landingStatistic->delete();
        $auditService->log('landing_statistic_deleted', 'landing-cms', 'DELETE', $landingStatistic, $old, []);
        LandingContentCache::forget();

        return $this->success(null, 'Statistik berhasil dihapus.');
    }

    public function reorder(Request $request, LandingStatistic $landingStatistic, AuditService $auditService)
    {
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $landingStatistic->moveOrder($data['direction']);
        $auditService->log('landing_statistic_reordered', 'landing-cms', 'UPDATE', $landingStatistic, [], $landingStatistic->refresh()->toArray());
        LandingContentCache::forget();

        return $this->success(null, 'Urutan statistik berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:150'],
            'value' => ['required', 'numeric'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'source' => ['sometimes', Rule::in(['manual', 'residents_count', 'units_count', 'clusters_count'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
