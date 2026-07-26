<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Broadcast;
use App\Models\CollectorAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\SupervisorAssignmentService;
use App\Services\WhatsAppBroadcastService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BroadcastController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Broadcast::query()
            ->with('sender')
            ->when($request->query('type'), fn ($q, $value) => $q->where('type', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(Broadcast $broadcast)
    {
        return $this->success($broadcast->load(['sender', 'recipients']));
    }

    public function store(Request $request, SupervisorAssignmentService $scopeService, WhatsAppBroadcastService $service)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([Broadcast::TYPE_COLLECTOR, Broadcast::TYPE_RESIDENT, Broadcast::TYPE_ANNOUNCEMENT])],
            'message' => ['required', 'string', 'max:1000'],
            'cluster_id' => ['nullable', 'exists:clusters,id'],
            'collector_ids' => ['nullable', 'array'],
            'collector_ids.*' => ['exists:users,id'],
            'unit_ids' => ['nullable', 'array'],
            'unit_ids.*' => ['exists:units,id'],
        ]);

        $recipients = match ($data['type']) {
            Broadcast::TYPE_COLLECTOR => $this->resolveCollectorRecipients($request->user(), $scopeService, $data),
            Broadcast::TYPE_RESIDENT => $this->resolveResidentRecipients($data),
            Broadcast::TYPE_ANNOUNCEMENT => $this->resolveAnnouncementRecipients($request->user(), $scopeService),
        };

        $broadcast = Broadcast::query()->create([
            'type' => $data['type'],
            'message' => $data['message'],
            'target_criteria' => [
                'cluster_id' => $data['cluster_id'] ?? null,
                'collector_ids' => $data['collector_ids'] ?? null,
                'unit_ids' => $data['unit_ids'] ?? null,
            ],
            'sender_id' => $request->user()->id,
        ]);

        $broadcast = $service->send($broadcast, $recipients, viaWhatsApp: $data['type'] !== Broadcast::TYPE_ANNOUNCEMENT);

        return $this->success($broadcast->load('recipients'), 'Broadcast berhasil dikirim.', 201);
    }

    private function resolveCollectorRecipients($user, SupervisorAssignmentService $scopeService, array $data): array
    {
        // Always intersected with this caller's own scope - for root/admin_estate that scope is
        // "every collector" (SupervisorAssignmentService's exemption), so this is a no-op for
        // them, but it prevents a Supervisor from broadcasting to collector_ids outside their
        // assigned clusters just by passing the id explicitly.
        $inScope = $scopeService->collectorIdsFor($user);
        $collectorIds = ! empty($data['collector_ids']) ? array_values(array_intersect($data['collector_ids'], $inScope)) : $inScope;

        if (! empty($data['cluster_id'])) {
            $inCluster = CollectorAssignment::query()->active()->currentlyEffective()->where('cluster_id', $data['cluster_id'])->pluck('collector_id')->unique()->all();
            $collectorIds = array_values(array_intersect($collectorIds, $inCluster));
        }

        return User::query()->whereIn('id', $collectorIds)->with('collectorProfile')->get()
            ->map(fn (User $collector) => [
                'type' => 'collector',
                'id' => (string) $collector->id,
                'name' => $collector->name,
                'phone' => $collector->collectorProfile?->whatsapp_number ?: $collector->phone,
            ])->all();
    }

    private function resolveResidentRecipients(array $data): array
    {
        $query = Unit::query()->with('resident')->whereNotNull('resident_id');

        if (! empty($data['unit_ids'])) {
            $query->whereIn('id', $data['unit_ids']);
        } elseif (! empty($data['cluster_id'])) {
            $query->where('cluster_id', $data['cluster_id']);
        }

        return $query->get()->filter(fn (Unit $unit) => $unit->resident)->map(fn (Unit $unit) => [
            'type' => 'resident',
            'id' => $unit->resident_id,
            'name' => $unit->resident->name,
            'phone' => $unit->resident->phone,
        ])->unique('id')->values()->all();
    }

    private function resolveAnnouncementRecipients($user, SupervisorAssignmentService $scopeService): array
    {
        $collectorIds = $scopeService->collectorIdsFor($user);

        return User::query()->whereIn('id', $collectorIds)->get()
            ->map(fn (User $collector) => [
                'type' => 'collector',
                'id' => (string) $collector->id,
                'name' => $collector->name,
                'phone' => $collector->phone,
            ])->all();
    }
}
