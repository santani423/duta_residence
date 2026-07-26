<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorLocation;
use Illuminate\Http\Request;

class CollectorLocationController extends Controller
{
    use ApiResponse;

    public function store(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
        ]);

        $location = CollectorLocation::query()->create([
            ...$data,
            'collector_id' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return $this->success($location, 'Lokasi berhasil dikirim.', 201);
    }

    public function index(Request $request)
    {
        $query = CollectorLocation::query()
            ->with('collector')
            ->when($request->query('collector_id'), fn ($q, $value) => $q->where('collector_id', $value))
            ->when($request->query('since'), fn ($q, $value) => $q->where('recorded_at', '>=', $value));

        return $this->paginated($query->latest('recorded_at')->paginate($request->integer('per_page', 50)));
    }

    /** Latest ping per active collector, for the live monitoring map. */
    public function latest()
    {
        $latestIds = CollectorLocation::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('collector_id');

        $locations = CollectorLocation::query()
            ->with('collector')
            ->whereIn('id', $latestIds)
            ->get();

        return $this->success($locations);
    }
}
