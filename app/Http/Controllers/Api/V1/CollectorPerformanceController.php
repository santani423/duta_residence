<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Rules\CollectorUser;
use App\Services\CollectionScopeService;
use App\Services\CollectorPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorPerformanceController extends Controller
{
    use ApiResponse;

    /**
     * Performa satu collector. Collector hanya boleh melihat dirinya sendiri; supervisor hanya
     * collector dalam cakupannya (403 selain itu).
     */
    public function index(Request $request, CollectorPerformanceService $service, CollectionScopeService $scope)
    {
        $data = $request->validate([
            'collector_id' => ['required', new CollectorUser],
            'period_type' => ['required', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['required', 'date'],
        ]);

        $collectorId = (int) $data['collector_id'];
        $scope->assertCollectorInScope($request->user(), $collectorId);

        $collector = User::query()->findOrFail($collectorId);

        return $this->success($service->achievementFor($collector, $data['period_type'], $data['period_start']));
    }

    /** Dipakai Flutter (dashboard & layar performa collector) — key lama wajib tetap ada. */
    public function mine(Request $request, CollectorPerformanceService $service)
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::in(CollectorPerformanceService::PERIOD_TYPES)],
            'period_start' => ['required', 'date'],
        ]);

        return $this->success($service->achievementFor($request->user(), $data['period_type'], $data['period_start']));
    }
}
