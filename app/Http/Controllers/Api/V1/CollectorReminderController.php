<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorReminder;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use Illuminate\Http\Request;

class CollectorReminderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = CollectorReminder::query()
            ->with(['unit.cluster', 'resident', 'sender'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value));

        return $this->paginated($query->latest('sent_at')->paginate($request->integer('per_page', 15)));
    }

    /**
     * Logs a reminder after the client has already opened the wa.me deep link — this
     * endpoint never sends anything itself, it just records that the collector did.
     */
    public function store(Request $request, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_id' => ['nullable', 'exists:billings,id'],
            'message' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $unit = Unit::query()->findOrFail($data['unit_id']);
        $assignmentService->assertUnitAssigned($request->user(), $unit->id);

        $reminder = CollectorReminder::query()->create([
            ...$data,
            'resident_id' => $unit->resident_id,
            'sent_at' => now(),
            'sent_by' => $request->user()->id,
        ]);

        $auditService->log('collector_reminder_sent', 'collector-reminders', 'CREATE', $reminder, [], $reminder->toArray());

        return $this->success($reminder, 'Pengingat berhasil dicatat.', 201);
    }
}
