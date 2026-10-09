<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PaymentPromise;
use App\Models\Resident;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\ResidentDetailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentPromiseController extends Controller
{
    use ApiResponse;

    /** Status yang boleh dipakai collector saat membuat / mengubah janji. */
    private const COLLECTOR_CREATE_STATUSES = ['pending'];

    private const COLLECTOR_UPDATE_STATUSES = ['pending', 'rescheduled'];

    public function index(Request $request, Resident $resident, ResidentDetailService $service, CollectorAssignmentService $assignmentService)
    {
        $unitIds = $service->unitIds($resident, $request->query('unit_id'));

        if ($request->user()->hasRole('collector')) {
            $unitIds = array_values(array_intersect($unitIds, $assignmentService->unitIdsFor($request->user())));
        }

        $query = PaymentPromise::query()
            ->with(['unit.cluster', 'billing', 'creator'])
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest('promised_date')->paginate($request->integer('per_page', 15)));
    }

    /**
     * User ber-role collector hanya boleh mencatat janji berstatus `pending`
     * untuk unit yang ditugaskan kepadanya; `collector_id` diisi collector tersebut. Status
     * `fulfilled`/`broken` hanya ditetapkan staf (verifikasi pembayaran / evaluasi PTP).
     * Aplikasi Flutter (ptp_form_screen) selalu mengirim `status`, default `pending`.
     */
    public function store(Request $request, Unit $unit, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        $user = $request->user();
        $isCollector = $user->hasRole('collector');

        if ($isCollector) {
            $assignmentService->assertUnitAssigned($user, $unit->id);
        }

        $data = $this->validatePromise($request, $isCollector);

        if ($isCollector) {
            $this->assertCollectorStatus($data['status'], self::COLLECTOR_CREATE_STATUSES);
            $data['collector_id'] = $user->id;
        }

        $data['unit_id'] = $unit->id;
        $data['created_by'] = $user->id;
        $data += $this->statusTimestamps($data['status']);

        $promise = PaymentPromise::query()->create($data);
        $auditService->log('payment_promise_created', 'payment-promises', 'CREATE', $promise, [], $promise->toArray());

        return $this->success($promise->load(['unit.cluster', 'billing', 'creator']), 'Janji pembayaran berhasil dicatat.', 201);
    }

    /**
     * Collector hanya boleh mengubah janji di unit yang ditugaskan, dan hanya ke status
     * `pending`/`rescheduled`; janji yang sudah `fulfilled`/`broken` tidak dapat diubah collector.
     */
    public function update(Request $request, PaymentPromise $promise, AuditService $auditService, CollectorAssignmentService $assignmentService)
    {
        $user = $request->user();
        $isCollector = $user->hasRole('collector');

        if ($isCollector) {
            $assignmentService->assertUnitAssigned($user, $promise->unit_id);

            if (in_array($promise->status, ['fulfilled', 'broken'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Janji pembayaran yang sudah ditepati atau diingkari tidak dapat diubah oleh kolektor.'],
                ]);
            }
        }

        $data = $this->validatePromise($request, $isCollector);

        if ($isCollector) {
            $this->assertCollectorStatus($data['status'], self::COLLECTOR_UPDATE_STATUSES);
        }

        if ($data['status'] !== $promise->status) {
            $data += $this->statusTimestamps($data['status']);
        }
        $data['updated_by'] = $user->id;

        $old = $promise->toArray();
        $promise->update($data);
        $auditService->log('payment_promise_updated', 'payment-promises', 'UPDATE', $promise, $old, $promise->refresh()->toArray());

        return $this->success($promise->load(['unit.cluster', 'billing', 'creator']), 'Janji pembayaran berhasil diperbarui.');
    }

    private function assertCollectorStatus(string $status, array $allowed): void
    {
        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['Kolektor hanya dapat menyimpan janji pembayaran dengan status: '.implode(', ', $allowed).'. Status ditepati/diingkari ditetapkan oleh petugas.'],
            ]);
        }
    }

    /** Cap waktu transisi status (dipakai metrik performa PTP). */
    private function statusTimestamps(string $status): array
    {
        return match ($status) {
            'fulfilled' => ['fulfilled_at' => now()],
            'broken' => ['broken_at' => now()],
            default => [],
        };
    }

    private function validatePromise(Request $request, bool $isCollector = false): array
    {
        $data = $request->validate([
            'billing_id' => ['nullable', 'exists:billings,id'],
            'promised_amount' => ['required', 'numeric', 'min:0'],
            'promised_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:30'],
            'reason' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            // Collector boleh tidak mengirim status (default pending); staf tetap wajib.
            'status' => [$isCollector ? 'nullable' : 'required', Rule::in(['pending', 'fulfilled', 'broken', 'rescheduled'])],
            'notes' => ['nullable', 'string'],
        ]);
        $data['status'] ??= 'pending';

        return $data;
    }
}
