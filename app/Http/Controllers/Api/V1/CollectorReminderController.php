<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorReminder;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectionScopeService;
use App\Services\CollectorAssignmentService;
use App\Support\Pagination;
use Illuminate\Http\Request;

class CollectorReminderController extends Controller
{
    use ApiResponse;

    /**
     * Riwayat pengingat WA, dibatasi ke unit dalam cakupan user. Collector boleh membaca
     * riwayat unit yang ditugaskan kepadanya (halaman Pengingat WA memanggil
     * `?unit_id=...`); unit di luar cakupan → 403, tanpa `unit_id` → hanya unit miliknya.
     * Supervisor → unit di clusternya, full scope → semua.
     */
    public function index(Request $request, CollectionScopeService $scope)
    {
        $user = $request->user();

        if ($unitId = $request->query('unit_id')) {
            $scope->assertUnitInScope($user, (string) $unitId);
        }

        $query = CollectorReminder::query()
            ->with(['unit.cluster', 'resident', 'sender'])
            ->tap(fn ($q) => $scope->constrainUnits($q, $user, 'collector_reminders.unit_id'))
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value));

        return $this->paginated($query->latest('sent_at')->paginate(Pagination::perPage($request)));
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

        if (! $unit->resident_id) {
            return $this->error('Unit belum memiliki penghuni terdaftar, tidak dapat mencatat pengingat.', 422);
        }

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
