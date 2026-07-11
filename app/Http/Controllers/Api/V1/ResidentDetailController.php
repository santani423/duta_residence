<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\MaintenanceRequest;
use App\Models\NotificationQueue;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Resident;
use App\Models\ResidentComplaint;
use App\Services\AuditService;
use App\Services\ResidentDetailService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResidentDetailController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ResidentDetailService $service) {}

    public function summary(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        return $this->success($this->service->summary($unitIds));
    }

    public function billings(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = Billing::query()
            ->with(['unit.cluster', 'status'])
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->when($request->query('billing_type'), fn ($q, $value) => $q->where('billing_type', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function transactions(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = PaymentTransaction::query()
            ->with(['unit.cluster', 'billings', 'verifier', 'creator'])
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function receipts(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = Receipt::query()->with('unit.cluster')->whereIn('unit_id', $unitIds);

        return $this->paginated($query->latest('transaction_date')->paginate($request->integer('per_page', 15)));
    }

    public function sendReceipt(Request $request, Resident $resident, Receipt $receipt, AuditService $auditService)
    {
        abort_unless(in_array($receipt->unit_id, $this->service->unitIds($resident), true), 404);

        $recipient = $resident->email ?: $resident->phone;
        abort_if(blank($recipient), 422, 'Penghuni tidak memiliki email atau telepon untuk menerima kuitansi.');

        $notification = NotificationQueue::query()->create([
            'unit_id' => $receipt->unit_id,
            'user_id' => null,
            'type' => 'receipt_shared',
            'channel' => 'in_app',
            'recipient' => $recipient,
            'message' => "Kuitansi {$receipt->number} telah dikirimkan kepada Anda.",
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $auditService->log('receipt_sent_to_resident', 'residents', 'SEND_RECEIPT', $resident, [], ['receipt_number' => $receipt->number]);

        return $this->success($notification, 'Kuitansi berhasil dikirimkan ke penghuni.');
    }

    public function complaints(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = ResidentComplaint::query()
            ->with(['unit', 'assignee'])
            ->withCount('comments')
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeComplaint(Request $request, Resident $resident, AuditService $auditService)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::in($this->service->unitIds($resident))],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string'],
            'division' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'target_date' => ['nullable', 'date'],
        ]);

        $unit = $resident->units()->where('id', $data['unit_id'])->first();
        $complaint = ResidentComplaint::query()->create([
            ...$data,
            'user_id' => $unit->users()->value('id'),
            'status' => 'submitted',
            'created_by' => $request->user()->id,
        ]);

        $auditService->log('resident_complaint_created', 'complaints', 'CREATE', $complaint, [], $complaint->toArray());

        return $this->success($complaint->load(['unit', 'assignee']), 'Komplain berhasil dibuat.', 201);
    }

    public function maintenanceRequests(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = MaintenanceRequest::query()
            ->with('unit')
            ->whereIn('unit_id', $unitIds)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeMaintenanceRequest(Request $request, Resident $resident, AuditService $auditService)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::in($this->service->unitIds($resident))],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['low', 'normal', 'high', 'emergency'])],
            'preferred_schedule' => ['nullable', 'date'],
        ]);

        $unit = $resident->units()->where('id', $data['unit_id'])->first();
        $maintenance = MaintenanceRequest::query()->create([
            ...$data,
            'unit_label' => "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}",
            'user_id' => $unit->users()->value('id'),
            'status' => 'submitted',
            'created_by' => $request->user()->id,
        ]);

        $auditService->log('maintenance_request_created', 'maintenance', 'CREATE', $maintenance, [], $maintenance->toArray());

        return $this->success($maintenance->load('unit'), 'Permintaan layanan berhasil dibuat.', 201);
    }

    public function sendNotification(Request $request, Resident $resident, AuditService $auditService)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::in($this->service->unitIds($resident))],
            'message' => ['required', 'string', 'max:500'],
        ]);

        $notification = NotificationQueue::query()->create([
            'unit_id' => $data['unit_id'],
            'user_id' => null,
            'type' => 'manual_notification',
            'channel' => 'in_app',
            'recipient' => $resident->email ?: $resident->phone,
            'message' => $data['message'],
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $auditService->log('notification_sent_to_resident', 'residents', 'SEND_NOTIFICATION', $resident, [], ['message' => $data['message']]);

        return $this->success($notification, 'Notifikasi berhasil dikirim.', 201);
    }

    public function notifications(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        $query = NotificationQueue::query()->whereIn('unit_id', $unitIds);

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function activity(Request $request, Resident $resident)
    {
        $unitIds = $this->service->unitIds($resident, $request->query('unit_id'));

        return $this->paginated($this->service->activity($resident, $unitIds)->latest()->paginate($request->integer('per_page', 15)));
    }
}
