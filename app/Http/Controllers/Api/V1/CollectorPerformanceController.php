<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\CollectorPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectorPerformanceController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CollectorPerformanceService $service)
    {
        $data = $request->validate([
            'collector_id' => ['required', 'exists:users,id'],
            'period_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'period_start' => ['required', 'date'],
        ]);

        $collector = User::query()->findOrFail($data['collector_id']);

        return $this->success($service->achievementFor($collector, $data['period_type'], $data['period_start']));
    }

    public function mine(Request $request, CollectorPerformanceService $service)
    {
        $data = $request->validate([
            'period_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'period_start' => ['required', 'date'],
        ]);

        return $this->success($service->achievementFor($request->user(), $data['period_type'], $data['period_start']));
    }
}
