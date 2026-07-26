<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorLocation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        $this->assertWithinWorkingHours($request->user());

        $location = CollectorLocation::query()->create([
            ...$data,
            'collector_id' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return $this->success($location, 'Lokasi berhasil dikirim.', 201);
    }

    /**
     * "Jangan melakukan pelacakan di luar jam kerja atau tanpa izin yang sesuai" - a per-collector
     * duty window (collector_profiles.duty_start_time/duty_end_time) overrides the system-wide
     * default (config('collector.default_working_hours')); either side left null means "no
     * restriction" for that collector. This is enforced server-side (not just a UI convenience)
     * so a ping sent while off-duty is rejected before it's ever persisted.
     */
    private function assertWithinWorkingHours($collector): void
    {
        $profile = $collector->collectorProfile;
        $default = config('collector.default_working_hours');

        $start = $profile?->duty_start_time ?? $default['start'];
        $end = $profile?->duty_end_time ?? $default['end'];

        if (! $start || ! $end) {
            return;
        }

        $now = now()->format('H:i');

        if ($now < substr($start, 0, 5) || $now > substr($end, 0, 5)) {
            throw ValidationException::withMessages([
                'recorded_at' => ["Pelacakan lokasi hanya diizinkan pada jam kerja ({$start} - {$end})."],
            ]);
        }
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
