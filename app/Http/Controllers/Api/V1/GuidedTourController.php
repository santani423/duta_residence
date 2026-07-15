<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\GuidedTour;
use App\Models\UserTourProgress;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GuidedTourController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $roles = $request->user()->getRoleNames()->all();
        $progress = UserTourProgress::query()->where('user_id', $request->user()->id)->get()->keyBy('guided_tour_id');

        $tours = GuidedTour::query()
            ->with('steps')
            ->where('is_active', true)
            ->visibleToRoles($roles)
            ->orderBy('module')
            ->orderBy('order')
            ->get()
            ->map(fn (GuidedTour $tour) => [
                ...$tour->toArray(),
                'progress' => $progress->get($tour->id)?->only(['status', 'last_step', 'completed_at']),
            ]);

        return $this->success($tours->values());
    }

    public function progress(Request $request, GuidedTour $tour, AuditService $auditService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'skipped'])],
            'last_step' => ['nullable', 'integer', 'min:0'],
        ]);

        $entry = UserTourProgress::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'guided_tour_id' => $tour->id],
            [
                'status' => $data['status'],
                'last_step' => $data['last_step'] ?? null,
                'completed_at' => $data['status'] === 'completed' ? now() : null,
            ]
        );
        $auditService->log('guided_tour_progress', 'guided-tours', 'UPDATE', $entry, [], $entry->toArray());

        return $this->success($entry, 'Progres tur berhasil disimpan.');
    }

    public function adminIndex(Request $request)
    {
        $query = GuidedTour::query()->with('steps')
            ->when($request->query('module'), fn ($q, $value) => $q->where('module', $value));

        return $this->paginated($query->orderBy('module')->orderBy('order')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateTour($request);
        $steps = $data['steps'] ?? [];
        unset($data['steps']);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $tour = DB::transaction(function () use ($data, $steps) {
            $tour = GuidedTour::query()->create($data);
            $this->syncSteps($tour, $steps);

            return $tour;
        });

        $auditService->log('guided_tour_created', 'guided-tours', 'CREATE', $tour, [], $tour->toArray());

        return $this->success($tour->load('steps'), 'Tur panduan berhasil dibuat.', 201);
    }

    public function update(Request $request, GuidedTour $tour, AuditService $auditService)
    {
        $data = $this->validateTour($request, $tour);
        $steps = $data['steps'] ?? [];
        unset($data['steps']);
        $data['updated_by'] = $request->user()->id;
        $old = $tour->toArray();

        DB::transaction(function () use ($tour, $data, $steps) {
            $tour->update($data);
            $this->syncSteps($tour, $steps);
        });

        $auditService->log('guided_tour_updated', 'guided-tours', 'UPDATE', $tour, $old, $tour->refresh()->toArray());

        return $this->success($tour->load('steps'), 'Tur panduan berhasil diperbarui.');
    }

    public function destroy(GuidedTour $tour, AuditService $auditService)
    {
        $old = $tour->toArray();
        $tour->delete();
        $auditService->log('guided_tour_deleted', 'guided-tours', 'DELETE', $tour, $old, []);

        return $this->success(null, 'Tur panduan berhasil dihapus.');
    }

    private function syncSteps(GuidedTour $tour, array $steps): void
    {
        $tour->steps()->delete();
        foreach ($steps as $index => $step) {
            $tour->steps()->create([
                'target' => $step['target'],
                'title' => $step['title'],
                'content' => $step['content'],
                'placement' => $step['placement'] ?? 'bottom',
                'order' => $index,
            ]);
        }
    }

    private function validateTour(Request $request, ?GuidedTour $tour = null): array
    {
        return $request->validate([
            'module' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'is_active' => ['sometimes', 'boolean'],
            'auto_start' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'steps' => ['nullable', 'array'],
            'steps.*.target' => ['required_with:steps', 'string', 'max:150'],
            'steps.*.title' => ['required_with:steps', 'string', 'max:150'],
            'steps.*.content' => ['required_with:steps', 'string'],
            'steps.*.placement' => ['nullable', Rule::in(['top', 'bottom', 'left', 'right'])],
        ]);
    }
}
