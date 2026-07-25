<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\EmergencyAlert;
use App\Models\ResidentComplaint;
use App\Models\ResidentComplaintComment;
use App\Models\ManagedFile;
use App\Models\MaintenanceRequest;
use App\Models\NotificationQueue;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Resident;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\PenaltyService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\ResidentAccountService;
use App\Services\UnitOwnershipSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResidentPortalController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PenaltyService $penaltyService) {}

    public function dashboard(Request $request)
    {
        $unit = $this->unit($request);
        $billings = $this->billingBaseQuery($unit)->get();
        // Hitung sekali per tagihan lalu pakai ulang, alih-alih memanggil PenaltyService
        // berkali-kali untuk baris yang sama di setiap filter/sum di bawah.
        $billingCalcs = $billings->map(fn (Billing $billing) => ['billing' => $billing, 'calc' => $this->penaltyService->calculateInvoiceTotal($billing)]);
        $outstanding = $billingCalcs->filter(fn (array $row) => $row['billing']->isOutstanding());
        $overdue = $billingCalcs->filter(fn (array $row) => $row['calc']['status'] === 'overdue');
        $payments = PaymentTransaction::query()->where('unit_id', $unit->id)->latest()->get();
        $activeComplaints = $unit->complaints()->whereNotIn('status', ['closed', 'resolved', 'rejected'])->count();
        $activeMaintenance = $unit->maintenanceRequests()->whereNotIn('status', ['completed', 'closed', 'rejected'])->count();

        return $this->success([
            'resident' => $this->unitProfile($unit, $request->user()),
            'estate' => [
                'name' => 'Grand Duta Residence',
                'cluster' => $unit->cluster?->name,
            ],
            'property' => $this->propertyPayload($unit),
            'billing_summary' => [
                'active_total' => $outstanding->sum(fn (array $row) => $row['calc']['total_outstanding']),
                'unpaid_count' => $outstanding->count(),
                'overdue_count' => $overdue->count(),
                'overdue_total' => $overdue->sum(fn (array $row) => $row['calc']['total_outstanding']),
                'penalty_rules_info' => [
                    'current_month' => 'Tagihan bulan berjalan tidak dikenakan denda.',
                    'tier_1_2_months' => 'Tunggakan 1-2 bulan dikenakan denda Rp15.000.',
                    'tier_3_plus_months' => 'Tunggakan 3 bulan atau lebih dikenakan denda Rp30.000.',
                ],
                'latest' => $billings->sortByDesc('created_at')->take(5)->map(fn (Billing $billing) => $this->invoicePayload($billing))->values(),
            ],
            'payment_summary' => [
                'successful_total' => $payments->where('status', 'paid')->sum('total'),
                'processing_count' => $payments->whereIn('status', ['pending'])->count(),
                'manual_waiting_count' => $payments->where('status', 'waiting_verification')->count(),
                'latest' => $payments->take(5)->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction))->values(),
            ],
            'service_summary' => [
                'active_complaints' => $activeComplaints,
                'active_maintenance_requests' => $activeMaintenance,
                'usage' => [
                    ['label' => 'Komplain aktif', 'value' => $activeComplaints],
                    ['label' => 'Maintenance aktif', 'value' => $activeMaintenance],
                    ['label' => 'Dokumen tersedia', 'value' => count($this->documentsForUnit($unit))],
                ],
            ],
            'latest_documents' => array_slice($this->documentsForUnit($unit), 0, 5),
            'latest_notifications' => $this->notificationQuery($request, $unit)->latest()->limit(5)->get(),
            'latest_activity' => $this->activityQuery($request)->latest()->limit(8)->get(),
            'payment_config' => PaymentGatewaySetting::current()->publicConfig(),
        ]);
    }

    public function account(Request $request)
    {
        $unit = $this->unit($request);

        return $this->success([
            'account' => $this->unitProfile($unit, $request->user()),
            'property' => $this->propertyPayload($unit),
            'billings' => $this->billingBaseQuery($unit)->latest()->limit(10)->get()->map(fn (Billing $billing) => $this->invoicePayload($billing)),
            'payments' => PaymentTransaction::query()->where('unit_id', $unit->id)->latest()->limit(10)->get()->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction)),
            'payment_methods' => PaymentGatewaySetting::current()->publicConfig(),
            'documents' => $this->documentsForUnit($unit),
            'security' => [
                'last_login_at' => $request->user()->last_login_at,
                'last_login_ip' => $request->user()->last_login_ip,
            ],
            'notifications' => $this->notificationQuery($request, $unit)->latest()->limit(10)->get(),
            'activity' => $this->activityQuery($request)->latest()->limit(10)->get(),
        ]);
    }

    public function profile(Request $request)
    {
        $unit = $this->unit($request);

        return $this->success($this->unitProfile($unit, $request->user()));
    }

    public function updateProfile(Request $request, AuditService $auditService)
    {
        $unit = $this->unit($request);
        $user = $request->user();
        $resident = $user->resident ?: $unit->resident;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'id_card_address' => ['nullable', 'string', 'max:200'],
            'language_preference' => ['nullable', Rule::in(['id', 'en'])],
            'theme_preference' => ['nullable', Rule::in(['light', 'dark', 'system'])],
            'notification_preferences' => ['nullable', 'array'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $oldUser = $user->toArray();
        $oldResident = $resident->toArray();

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
            'email' => $data['email'] ?? $user->email,
            'language_preference' => $data['language_preference'] ?? $user->language_preference,
            'theme_preference' => $data['theme_preference'] ?? $user->theme_preference,
            'notification_preferences' => $data['notification_preferences'] ?? $user->notification_preferences,
        ]);

        $resident->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $resident->phone,
            'email' => $data['email'] ?? $resident->email,
            'id_card_address' => $data['id_card_address'] ?? $resident->id_card_address,
            'updated_by' => $user->id,
        ]);

        if ($request->hasFile('photo')) {
            $this->storeManagedFile($request->file('photo'), $user, $resident, 'resident-profiles');
        }

        $auditService->log('resident_profile_updated', 'resident-profile', 'UPDATE', $resident, $oldResident, $resident->refresh()->toArray());
        $auditService->log('resident_account_updated', 'resident-account', 'UPDATE', $user, $oldUser, $user->refresh()->toArray());

        return $this->success($this->unitProfile($unit, $user->refresh()), 'Profil berhasil diperbarui.');
    }

    public function property(Request $request)
    {
        return $this->success(
            $this->residentUnits($request)->map(fn (Unit $unit) => $this->propertyPayload($unit))->values()
        );
    }

    public function bills(Request $request)
    {
        $unit = $this->unit($request);
        $outstandingBillings = $this->billingBaseQuery($unit)->get()->filter(fn (Billing $billing) => $billing->isOutstanding());
        $billingSummary = [
            'total_outstanding' => $outstandingBillings->sum(fn (Billing $billing) => $this->penaltyService->calculateInvoiceTotal($billing)['total_outstanding']),
            'unpaid_count' => $outstandingBillings->count(),
        ];

        $query = $this->billingBaseQuery($unit)
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('id', $value)
                ->orWhere('billing_type', 'like', "%{$value}%")))
            ->when($request->query('period'), function (Builder $q, string $period) {
                [$year, $month] = array_pad(explode('-', $period), 2, null);
                $q->where('year', $year)->when($month, fn (Builder $inner) => $inner->where('month', (int) $month));
            });

        if ($status = $request->query('status')) {
            $query = $query->latest()->get()->filter(fn (Billing $billing) => $this->penaltyService->invoiceStatus($billing) === $status)->values();

            return $this->success($query->map(fn (Billing $billing) => $this->invoicePayload($billing))->values(), 'Data berhasil ditemukan.', 200, $billingSummary);
        }

        $paginator = $query
            ->when($request->query('sort') === 'due_date', fn (Builder $q) => $q->orderBy('year')->orderBy('month'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $paginator->setCollection($paginator->getCollection()->map(fn (Billing $billing) => $this->invoicePayload($billing)));

        return $this->paginated($paginator, 'Data berhasil ditemukan.', $billingSummary);
    }

    public function invoice(Request $request, Billing $billing)
    {
        $billing = $this->ownedBilling($request, $billing);

        return $this->success([
            ...$this->invoicePayload($billing),
            'resident' => $this->unitProfile($billing->unit, $request->user()),
            'estate' => ['name' => 'Grand Duta Residence'],
            'unit' => $this->propertyPayload($billing->unit),
            'payment_history' => $billing->paymentTransactions->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction)),
        ]);
    }

    public function downloadInvoice(Request $request, Billing $billing)
    {
        $billing = $this->ownedBilling($request, $billing);
        $billing->load('status');
        $penaltyDetail = $this->penaltyService->calculateInvoiceTotal($billing);

        return Pdf::loadHTML(view('pdf.resident-invoice', compact('billing', 'penaltyDetail'))->render())
            ->download("Invoice-{$billing->id}.pdf");
    }

    public function paymentConfig()
    {
        return $this->success(PaymentGatewaySetting::current()->publicConfig(), 'Konfigurasi pembayaran berhasil ditemukan.');
    }

    public function createPayment(Request $request, Billing $billing, PaymentGatewayFactory $factory)
    {
        $billing = $this->ownedBilling($request, $billing);
        $this->assertIsDesignatedPayer($request, $billing->unit);
        $setting = PaymentGatewaySetting::current();
        $data = $request->validate([
            'provider' => ['nullable', Rule::in(['manual', 'xendit', 'midtrans'])],
        ]);
        $provider = $data['provider'] ?? $setting->active_gateway;

        if (! $setting->is_active || ! in_array($provider, $setting->availableGateways(), true)) {
            throw ValidationException::withMessages(['provider' => ['Metode pembayaran sedang tidak tersedia.']]);
        }

        if (! $billing->isOutstanding() || blank($billing->approved_at)) {
            throw ValidationException::withMessages(['billing' => ['Invoice tidak dapat dibayar.']]);
        }

        $transaction = DB::transaction(function () use ($billing, $request, $setting, $provider, $factory) {
            $calc = $this->penaltyService->calculateInvoiceTotal($billing);
            $transaction = PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'unit_id' => $billing->unit_id,
                'subtotal' => $calc['outstanding_principal'],
                'tax' => 0,
                'admin_fee' => (float) $setting->admin_fee,
                'total' => $calc['total_outstanding'] + (float) $setting->admin_fee,
                'currency' => $setting->currency,
                'payment_provider' => $provider,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);
            $transaction->billings()->sync([$billing->id]);

            return $factory->make($provider)->create($transaction);
        });

        return $this->success($this->paymentPayload($transaction->load('billings')), 'Transaksi pembayaran berhasil dibuat.', 201);
    }

    public function payments(Request $request)
    {
        $unit = $this->unit($request);
        $query = PaymentTransaction::query()
            ->with('billings')
            ->where('unit_id', $unit->id)
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('transaction_number', 'like', "%{$value}%")
                ->orWhere('invoice_number', 'like', "%{$value}%")
                ->orWhere('provider_reference', 'like', "%{$value}%")))
            ->when($request->query('status'), fn (Builder $q, $value) => $q->where('status', $value))
            ->when($request->query('provider'), fn (Builder $q, $value) => $q->where('payment_provider', $value));

        $paginator = $query->latest()->paginate($request->integer('per_page', 15));
        $paginator->setCollection($paginator->getCollection()->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction)));

        return $this->paginated($paginator);
    }

    public function payment(Request $request, PaymentTransaction $payment)
    {
        $payment = $this->ownedPayment($request, $payment);

        return $this->success([
            ...$this->paymentPayload($payment),
            'resident' => $this->unitProfile($payment->unit, $request->user()),
            'fee_breakdown' => [
                'subtotal' => (float) $payment->subtotal,
                'tax' => (float) $payment->tax,
                'admin_fee' => (float) $payment->admin_fee,
                'total' => (float) $payment->total,
            ],
            'status_history' => $this->paymentStatusHistory($payment),
        ]);
    }

    public function paymentStatus(Request $request, PaymentTransaction $payment)
    {
        $payment = $this->ownedPayment($request, $payment);

        return $this->success([
            'id' => $payment->id,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
            'expired_at' => $payment->expired_at,
            'history' => $this->paymentStatusHistory($payment),
        ]);
    }

    public function uploadManualProof(Request $request, PaymentTransaction $payment, AuditService $auditService)
    {
        $payment = $this->ownedPayment($request, $payment);
        $this->assertIsDesignatedPayer($request, $payment->unit);
        $setting = PaymentGatewaySetting::current();
        $extensions = implode(',', $setting->proof_allowed_extensions ?: ['jpg', 'jpeg', 'png', 'pdf']);
        $data = $request->validate([
            'sender_name' => ['required', 'string', 'max:100'],
            'sender_bank' => ['required', 'string', 'max:100'],
            'sender_account_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'manual_transfer_date' => ['required', 'date'],
            'proof' => ['required', 'file', "mimes:{$extensions}", 'max:'.$setting->proof_max_size_kb],
            'manual_notes' => ['nullable', 'string'],
        ]);

        if ($payment->payment_provider !== 'manual' || ! in_array($payment->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['payment' => ['Bukti hanya dapat diunggah untuk pembayaran manual yang masih menunggu.']]);
        }

        $old = $payment->toArray();
        $stored = $data['proof']->store('manual-payments', 'public');
        $this->storeManagedFile($data['proof'], $request->user(), $payment, 'manual-payments', $stored);
        $payment->update([
            'manual_proof_path' => $stored,
            'manual_transfer_date' => $data['manual_transfer_date'],
            'manual_notes' => trim(collect([
                "Pengirim: {$data['sender_name']}",
                "Bank: {$data['sender_bank']}",
                isset($data['sender_account_number']) ? "Rekening: {$data['sender_account_number']}" : null,
                "Nominal: {$data['amount']}",
                $data['manual_notes'] ?? null,
            ])->filter()->implode("\n")),
            'status' => 'waiting_verification',
            'verification_notes' => null,
        ]);
        $auditService->log('manual_payment_proof_uploaded', 'payments', 'UPLOAD_PROOF', $payment, $old, $payment->refresh()->toArray());

        return $this->success($this->paymentPayload($payment), 'Bukti pembayaran berhasil dikirim.');
    }

    public function downloadReceipt(Request $request, PaymentTransaction $payment)
    {
        $payment = $this->ownedPayment($request, $payment);

        return Pdf::loadHTML(view('pdf.resident-payment-receipt', compact('payment'))->render())
            ->download("Receipt-{$payment->transaction_number}.pdf");
    }

    public function downloadCashReceipt(Request $request, Receipt $receipt)
    {
        $unit = $this->unit($request);
        abort_if($receipt->unit_id !== $unit->id, 404);
        $receipt->load(['unit.cluster', 'billings', 'paymentTransaction.allocations.billing']);

        return Pdf::loadHTML(view('pdf.spt', compact('receipt'))->render())
            ->download("SPT-{$receipt->number}.pdf");
    }

    public function complaints(Request $request)
    {
        $unit = $this->unit($request);
        $query = $unit->complaints()->withCount('comments')
            ->when($request->query('status'), fn (Builder $q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeComplaint(Request $request, AuditService $auditService)
    {
        $unit = $this->unit($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('complaints', 'public') : null;
        $complaint = $unit->complaints()->create([
            ...collect($data)->except('attachment')->all(),
            'attachment_path' => $attachment,
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeManagedFile($request->file('attachment'), $request->user(), $complaint, 'complaints', $attachment);
        }

        $auditService->log('resident_complaint_created', 'complaints', 'CREATE', $complaint, [], $complaint->toArray());

        return $this->success($complaint->load('comments.user'), 'Komplain berhasil dibuat.', 201);
    }

    public function complaint(Request $request, ResidentComplaint $complaint)
    {
        $complaint = $this->ownedComplaint($request, $complaint);

        return $this->success($complaint->load(['comments.user', 'unit.cluster']));
    }

    public function updateComplaint(Request $request, ResidentComplaint $complaint, AuditService $auditService)
    {
        $complaint = $this->ownedComplaint($request, $complaint);

        if (! in_array($complaint->status, ['submitted', 'in_review'], true)) {
            throw ValidationException::withMessages(['complaint' => ['Komplain tidak dapat diubah pada status saat ini.']]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $old = $complaint->toArray();
        $payload = collect($data)->except('attachment')->all();

        if ($request->hasFile('attachment')) {
            $payload['attachment_path'] = $request->file('attachment')->store('complaints', 'public');
            $this->storeManagedFile($request->file('attachment'), $request->user(), $complaint, 'complaints', $payload['attachment_path']);
        }

        $complaint->update([...$payload, 'updated_by' => $request->user()->id]);
        $auditService->log('resident_complaint_updated', 'complaints', 'UPDATE', $complaint, $old, $complaint->refresh()->toArray());

        return $this->success($complaint->load('comments.user'), 'Komplain berhasil diperbarui.');
    }

    public function addComplaintComment(Request $request, ResidentComplaint $complaint, AuditService $auditService)
    {
        $complaint = $this->ownedComplaint($request, $complaint);
        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('complaints', 'public') : null;
        $comment = ResidentComplaintComment::query()->create([
            'resident_complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'attachment_path' => $attachment,
            'is_staff_response' => false,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeManagedFile($request->file('attachment'), $request->user(), $comment, 'complaints', $attachment);
        }

        $auditService->log('resident_complaint_comment_added', 'complaints', 'COMMENT', $complaint, [], $comment->toArray());

        return $this->success($comment->load('user'), 'Komentar berhasil ditambahkan.', 201);
    }

    public function closeComplaint(Request $request, ResidentComplaint $complaint, AuditService $auditService)
    {
        $complaint = $this->ownedComplaint($request, $complaint);
        $old = $complaint->toArray();
        $complaint->update(['status' => 'closed', 'closed_at' => now(), 'updated_by' => $request->user()->id]);
        $auditService->log('resident_complaint_closed', 'complaints', 'CLOSE', $complaint, $old, $complaint->refresh()->toArray());

        return $this->success($complaint, 'Komplain berhasil ditutup.');
    }

    public function maintenanceRequests(Request $request)
    {
        $unit = $this->unit($request);
        $query = $unit->maintenanceRequests()
            ->when($request->query('status'), fn (Builder $q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('category', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeMaintenanceRequest(Request $request, AuditService $auditService)
    {
        $unit = $this->unit($request);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['low', 'normal', 'high', 'emergency'])],
            'preferred_schedule' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('maintenance', 'public') : null;
        $maintenance = $unit->maintenanceRequests()->create([
            ...collect($data)->except('attachment')->all(),
            'unit_label' => "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}",
            'attachment_path' => $attachment,
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeManagedFile($request->file('attachment'), $request->user(), $maintenance, 'maintenance', $attachment);
        }

        $auditService->log('maintenance_request_created', 'maintenance', 'CREATE', $maintenance, [], $maintenance->toArray());

        return $this->success($maintenance, 'Permintaan maintenance berhasil dibuat.', 201);
    }

    public function maintenanceRequest(Request $request, MaintenanceRequest $maintenance)
    {
        return $this->success($this->ownedMaintenance($request, $maintenance));
    }

    public function updateMaintenanceRequest(Request $request, MaintenanceRequest $maintenance, AuditService $auditService)
    {
        $maintenance = $this->ownedMaintenance($request, $maintenance);

        if (! in_array($maintenance->status, ['submitted', 'in_review'], true)) {
            throw ValidationException::withMessages(['maintenance' => ['Permintaan tidak dapat diubah pada status saat ini.']]);
        }

        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['low', 'normal', 'high', 'emergency'])],
            'preferred_schedule' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);
        $old = $maintenance->toArray();
        $payload = collect($data)->except('attachment')->all();

        if ($request->hasFile('attachment')) {
            $payload['attachment_path'] = $request->file('attachment')->store('maintenance', 'public');
            $this->storeManagedFile($request->file('attachment'), $request->user(), $maintenance, 'maintenance', $payload['attachment_path']);
        }

        $maintenance->update([...$payload, 'updated_by' => $request->user()->id]);
        $auditService->log('maintenance_request_updated', 'maintenance', 'UPDATE', $maintenance, $old, $maintenance->refresh()->toArray());

        return $this->success($maintenance, 'Permintaan maintenance berhasil diperbarui.');
    }

    public function rateMaintenanceRequest(Request $request, MaintenanceRequest $maintenance, AuditService $auditService)
    {
        $maintenance = $this->ownedMaintenance($request, $maintenance);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'rating_notes' => ['nullable', 'string'],
        ]);
        $old = $maintenance->toArray();
        $maintenance->update($data);
        $auditService->log('maintenance_request_rated', 'maintenance', 'RATE', $maintenance, $old, $maintenance->refresh()->toArray());

        return $this->success($maintenance, 'Rating berhasil dikirim.');
    }

    public function documents(Request $request)
    {
        return $this->success($this->documentsForUnit($this->unit($request)));
    }

    public function notifications(Request $request)
    {
        $unit = $this->unit($request);

        return $this->paginated(
            $this->notificationQuery($request, $unit)
                ->when($request->query('read_status'), fn (Builder $q, $value) => $q->where('read_status', $value))
                ->when($request->query('type'), fn (Builder $q, $value) => $q->where('type', $value))
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function readNotification(Request $request, NotificationQueue $notification)
    {
        $unit = $this->unit($request);
        abort_if(! $this->notificationOwnedBy($notification, $request, $unit), 404);
        $notification->update(['read_status' => 'read']);

        return $this->success($notification, 'Notifikasi ditandai dibaca.');
    }

    public function readAllNotifications(Request $request)
    {
        $unit = $this->unit($request);
        $this->notificationQuery($request, $unit)->update(['read_status' => 'read']);

        return $this->success(null, 'Semua notifikasi ditandai dibaca.');
    }

    public function activity(Request $request)
    {
        return $this->paginated($this->activityQuery($request)->latest()->paginate($request->integer('per_page', 15)));
    }

    public function settings(Request $request)
    {
        $user = $request->user();

        return $this->success([
            'theme_preference' => $user->theme_preference,
            'language_preference' => $user->language_preference,
            'notification_preferences' => $user->notification_preferences ?: [
                'billing' => true,
                'payments' => true,
                'complaints' => true,
                'maintenance' => true,
                'documents' => true,
                'announcements' => true,
            ],
        ]);
    }

    public function updateSettings(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'theme_preference' => ['required', Rule::in(['light', 'dark', 'system'])],
            'language_preference' => ['required', Rule::in(['id', 'en'])],
            'notification_preferences' => ['required', 'array'],
        ]);
        $user = $request->user();
        $old = $user->toArray();
        $user->update($data);
        $auditService->log('resident_settings_updated', 'resident-settings', 'UPDATE', $user, $old, $user->refresh()->toArray());

        return $this->success($this->settings($request)->getData(true)['data'], 'Pengaturan berhasil disimpan.');
    }

    public function emergency(Request $request, AuditService $auditService)
    {
        $unit = $this->unit($request);
        $user = $request->user();
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'location_captured_at' => ['nullable', 'date'],
        ]);

        $alert = EmergencyAlert::query()->create([
            'unit_id' => $unit->id,
            'resident_id' => $user->resident_id ?: $unit->resident_id,
            'note' => $data['note'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'accuracy_meters' => $data['accuracy_meters'] ?? null,
            'location_captured_at' => $data['location_captured_at'] ?? null,
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $reporterName = ($user->resident ?: $unit->resident)?->name ?? $user->name;
        $unitLabel = "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}";

        NotificationQueue::query()->create([
            'unit_id' => $unit->id,
            'user_id' => null,
            'type' => 'emergency_alert',
            'channel' => 'in_app',
            'recipient' => 'staff',
            'message' => "SINYAL DARURAT dari {$reporterName} (Unit {$unitLabel})".($alert->note ? ": {$alert->note}" : '.'),
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $auditService->log('emergency_alert_triggered', 'emergency-alerts', 'CREATE', $alert, [], $alert->toArray());

        return $this->success($alert, 'Sinyal darurat berhasil dikirim ke admin.', 201);
    }

    /**
     * Dipoll berkala oleh aplikasi Android selagi layar "menunggu admin" aktif,
     * supaya status acknowledge/cancel admin muncul near-real-time tanpa websocket.
     */
    public function emergencyStatus(Request $request, EmergencyAlert $emergencyAlert)
    {
        return $this->success($this->ownedEmergencyAlert($request, $emergencyAlert)->load('acknowledger'));
    }

    public function cancelEmergency(Request $request, EmergencyAlert $emergencyAlert, AuditService $auditService)
    {
        $alert = $this->ownedEmergencyAlert($request, $emergencyAlert);

        if ($alert->status !== 'active') {
            return $this->success($alert->load('acknowledger'), 'Sinyal darurat ini sudah tidak aktif.');
        }

        $old = $alert->toArray();
        $alert->update([
            'status' => 'cancelled',
            'cancelled_by' => $request->user()->id,
            'cancelled_at' => now(),
        ]);
        $auditService->log('emergency_alert_cancelled', 'emergency-alerts', 'CANCEL', $alert, $old, $alert->refresh()->toArray());

        return $this->success($alert, 'Sinyal darurat dibatalkan.');
    }

    private function ownedEmergencyAlert(Request $request, EmergencyAlert $emergencyAlert): EmergencyAlert
    {
        $unit = $this->unit($request);
        abort_if($emergencyAlert->unit_id !== $unit->id, 404);

        return $emergencyAlert;
    }

    public function tenantInfo(Request $request)
    {
        $unit = $this->unit($request);
        $unit->loadMissing('tenantResident');
        $isOwner = $this->isOwner($request, $unit);

        return $this->success([
            'billing_payer' => $unit->billing_payer,
            'is_owner' => $isOwner,
            'viewer_role' => $isOwner ? 'pemilik' : 'penyewa',
            'has_tenant' => (bool) $unit->tenant_resident_id,
            'tenant' => $unit->tenantResident ? [
                'id' => $unit->tenantResident->id,
                'name' => $unit->tenantResident->name,
                'email' => $unit->tenantResident->email,
                'phone' => $unit->tenantResident->phone,
            ] : null,
        ]);
    }

    public function storeTenant(Request $request, AuditService $auditService, ResidentAccountService $accounts, UnitOwnershipSyncService $ownershipSync)
    {
        $unit = $this->unit($request);

        if (! $this->isOwner($request, $unit)) {
            throw ValidationException::withMessages(['tenant' => ['Hanya pemilik unit yang dapat menambahkan penyewa.']]);
        }

        if ($unit->tenant_resident_id) {
            throw ValidationException::withMessages(['tenant' => ['Unit ini sudah memiliki penyewa. Hapus penyewa saat ini terlebih dahulu.']]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('residents', 'email')],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('residents', 'phone')],
            'username' => ['nullable', 'string', 'min:4', 'max:50', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')],
        ]);
        $username = $data['username'] ?? null;
        unset($data['username']);

        $tenantResident = DB::transaction(function () use ($data, $accounts, $request, $auditService) {
            $data['id'] = $accounts->generateResidentId();
            $data['created_by'] = $request->user()->id;
            $resident = Resident::query()->create($data);
            $auditService->log('tenant_resident_created', 'residents', 'CREATE', $resident, [], $resident->toArray());

            return $resident;
        });

        $loginAccount = $accounts->createCustomerAccount($tenantResident, $auditService, $username);

        $old = $unit->toArray();
        $unit->update(['tenant_resident_id' => $tenantResident->id]);
        $auditService->log('unit_tenant_linked', 'units', 'UPDATE', $unit, $old, $unit->toArray());

        $ownershipSync->sync($unit->refresh(), $auditService);

        return $this->success([
            'tenant' => $tenantResident,
            'login_account' => $loginAccount,
        ], 'Penyewa berhasil ditambahkan.', 201);
    }

    public function updateBillingPayer(Request $request, AuditService $auditService)
    {
        $unit = $this->unit($request);

        if (! $this->isOwner($request, $unit)) {
            throw ValidationException::withMessages(['billing_payer' => ['Hanya pemilik unit yang dapat mengubah pengaturan penanggung jawab tagihan.']]);
        }

        $data = $request->validate([
            'billing_payer' => ['required', Rule::in(['pemilik', 'penyewa'])],
        ]);

        if ($data['billing_payer'] === 'penyewa' && ! $unit->tenant_resident_id) {
            throw ValidationException::withMessages(['billing_payer' => ['Tidak dapat menetapkan penyewa sebagai penanggung jawab karena unit ini belum memiliki penyewa.']]);
        }

        $old = $unit->toArray();
        $unit->update(['billing_payer' => $data['billing_payer']]);
        $auditService->log('unit_billing_payer_updated', 'units', 'UPDATE', $unit, $old, $unit->toArray());

        return $this->success(['billing_payer' => $unit->billing_payer], 'Pengaturan penanggung jawab tagihan berhasil disimpan.');
    }

    public function removeTenant(Request $request, AuditService $auditService, UnitOwnershipSyncService $ownershipSync)
    {
        $unit = $this->unit($request);

        if (! $this->isOwner($request, $unit)) {
            throw ValidationException::withMessages(['tenant' => ['Hanya pemilik unit yang dapat menghapus penyewa.']]);
        }

        if (! $unit->tenant_resident_id) {
            throw ValidationException::withMessages(['tenant' => ['Unit ini tidak memiliki penyewa.']]);
        }

        $old = $unit->toArray();
        $unit->update(['tenant_resident_id' => null, 'billing_payer' => 'pemilik']);
        $auditService->log('unit_tenant_removed', 'units', 'UPDATE', $unit, $old, $unit->toArray());

        $ownershipSync->sync($unit->refresh(), $auditService);

        return $this->success(null, 'Penyewa berhasil dihapus dari unit.');
    }

    private function isOwner(Request $request, Unit $unit): bool
    {
        return (string) $request->user()->resident_id === (string) $unit->resident_id;
    }

    private function unit(Request $request): Unit
    {
        $unit = $request->user()
            ->unit()
            ->with(['cluster', 'propertyType', 'occupancy', 'status', 'resident.district'])
            ->first();

        abort_if(! $unit, 403, 'Akun penghuni belum terhubung dengan data unit.');

        return $unit;
    }

    /**
     * Semua unit milik penghuni yang sama dengan akun ini (bukan hanya unit_id yang
     * dipakai untuk scoping billing/pembayaran), supaya halaman daftar properti bisa
     * menampilkan seluruh unit yang dimiliki satu penghuni.
     */
    private function residentUnits(Request $request)
    {
        $user = $request->user();
        $residentId = $user->resident_id ?? $user->unit?->resident_id;

        abort_if(! $residentId, 403, 'Akun penghuni belum terhubung dengan data unit.');

        return Unit::query()
            ->where('resident_id', $residentId)
            ->with(['cluster', 'propertyType', 'occupancy', 'status'])
            ->orderBy('id')
            ->get();
    }

    private function billingBaseQuery(Unit $unit): Builder
    {
        return Billing::query()
            ->with(['unit.cluster', 'paymentTransactions'])
            ->where('unit_id', $unit->id);
    }

    private function ownedBilling(Request $request, Billing $billing): Billing
    {
        $unit = $this->unit($request);
        abort_if($billing->unit_id !== $unit->id, 404);

        return $billing->load(['unit.cluster', 'unit.propertyType', 'paymentTransactions']);
    }

    private function ownedPayment(Request $request, PaymentTransaction $payment): PaymentTransaction
    {
        $unit = $this->unit($request);
        abort_if($payment->unit_id !== $unit->id, 404);

        return $payment->load(['unit.cluster', 'billings']);
    }

    private function assertIsDesignatedPayer(Request $request, Unit $unit): void
    {
        $isOwner = $this->isOwner($request, $unit);
        $payerIsOwner = $unit->billing_payer !== 'penyewa';

        if ($isOwner !== $payerIsOwner) {
            $expected = $payerIsOwner ? 'pemilik' : 'penyewa';
            throw ValidationException::withMessages([
                'billing_payer' => ["Pembayaran untuk unit ini hanya dapat dilakukan oleh {$expected} sesuai pengaturan penanggung jawab tagihan."],
            ]);
        }
    }

    private function ownedComplaint(Request $request, ResidentComplaint $complaint): ResidentComplaint
    {
        $unit = $this->unit($request);
        abort_if($complaint->unit_id !== $unit->id, 404);

        return $complaint;
    }

    private function ownedMaintenance(Request $request, MaintenanceRequest $maintenance): MaintenanceRequest
    {
        $unit = $this->unit($request);
        abort_if($maintenance->unit_id !== $unit->id, 404);

        return $maintenance;
    }

    private function unitProfile(Unit $unit, $user): array
    {
        $resident = $user->resident ?: $unit->resident;

        return [
            'id' => $unit->id,
            'name' => $resident?->name,
            'email' => $resident?->email ?: $user->email,
            'phone' => $resident?->phone ?: $user->phone,
            'resident_number' => $unit->id,
            'status' => $unit->status?->name,
            'estate' => 'Grand Duta Residence',
            'unit' => "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}",
            'joined_at' => $unit->created_at,
            'last_login_at' => $user->last_login_at,
            'address' => $resident?->id_card_address,
            'language_preference' => $user->language_preference,
            'theme_preference' => $user->theme_preference,
            'notification_preferences' => $user->notification_preferences,
        ];
    }

    private function propertyPayload(Unit $unit): array
    {
        return [
            'unit_id' => $unit->id,
            'va_number' => $unit->va_number,
            'estate' => 'Grand Duta Residence',
            'cluster' => $unit->cluster?->name,
            'block' => $unit->block,
            'lot_number' => $unit->lot_number,
            'unit_label' => "{$unit->cluster?->name} {$unit->block}/{$unit->lot_number}",
            'property_type' => $unit->propertyType?->name,
            'occupancy' => $unit->occupancy?->name,
            'building_area' => $unit->building_area,
            'land_area' => $unit->land_area,
            'handover_date' => $unit->handover_date,
            'status' => $unit->status?->name,
        ];
    }

    private function invoicePayload(Billing $billing): array
    {
        $calc = $this->penaltyService->calculateInvoiceTotal($billing);

        return [
            'id' => $billing->id,
            'invoice_number' => 'BIL-'.$billing->id,
            'va_number' => $billing->unit?->va_number,
            'billing_type' => $billing->billing_type,
            'period' => $calc['period'],
            'year' => $billing->year,
            'month' => $billing->month,
            'issued_at' => $billing->created_at,
            'due_date' => $calc['due_date'],
            'subtotal' => (float) $billing->amount,
            'tax' => 0,
            'admin_fee' => 0,
            'discount' => (float) $billing->discount,
            'principal_amount' => $calc['principal_amount'],
            'outstanding_principal' => $calc['outstanding_principal'],
            'overdue_months' => $calc['overdue_months'],
            'penalty' => $calc['penalty_amount'],
            'penalty_amount' => $calc['penalty_amount'],
            'outstanding_penalty' => $calc['outstanding_penalty'],
            'penalty_rule' => $calc['penalty_rule'],
            'total' => $calc['total_amount'],
            'total_amount' => $calc['total_amount'],
            'total_paid' => $calc['total_paid'],
            'total_outstanding' => $calc['total_outstanding'],
            'status' => $calc['status'],
            'approved_at' => $billing->approved_at,
            'paid_at' => $billing->paid_at,
        ];
    }

    private function paymentPayload(PaymentTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'transaction_number' => $transaction->transaction_number,
            'invoice_number' => $transaction->invoice_number,
            'billing_invoice_numbers' => $transaction->billings->map(fn (Billing $billing) => 'BIL-'.$billing->id)->values(),
            'payment_gateway' => $transaction->payment_provider,
            'payment_method' => $transaction->payment_method,
            'subtotal' => (float) $transaction->subtotal,
            'tax' => (float) $transaction->tax,
            'admin_fee' => (float) $transaction->admin_fee,
            'total' => (float) $transaction->total,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_url' => $transaction->payment_url,
            'provider_reference' => $transaction->provider_reference,
            'manual_proof_path' => $transaction->manual_proof_path,
            'manual_transfer_date' => $transaction->manual_transfer_date,
            'manual_notes' => $transaction->manual_notes,
            'verification_notes' => $transaction->verification_notes,
            'created_at' => $transaction->created_at,
            'paid_at' => $transaction->paid_at,
            'expired_at' => $transaction->expired_at,
        ];
    }

    private function paymentStatusHistory(PaymentTransaction $payment): array
    {
        return collect([
            ['status' => 'pending', 'changed_at' => $payment->created_at, 'notes' => 'Transaksi dibuat.'],
            $payment->manual_proof_path ? ['status' => 'waiting_verification', 'changed_at' => $payment->updated_at, 'notes' => 'Bukti pembayaran manual dikirim.'] : null,
            $payment->paid_at ? ['status' => 'paid', 'changed_at' => $payment->paid_at, 'notes' => 'Pembayaran berhasil.'] : null,
            $payment->status === 'rejected' ? ['status' => 'rejected', 'changed_at' => $payment->verified_at, 'notes' => $payment->verification_notes] : null,
            in_array($payment->status, ['failed', 'expired', 'cancelled', 'refunded'], true) ? ['status' => $payment->status, 'changed_at' => $payment->updated_at, 'notes' => 'Status diperbarui oleh gateway.'] : null,
        ])->filter()->values()->all();
    }

    private function documentsForUnit(Unit $unit): array
    {
        $invoices = $this->billingBaseQuery($unit)->latest()->limit(20)->get()->map(fn (Billing $billing) => [
            'id' => 'invoice-'.$billing->id,
            'type' => 'invoice',
            'name' => 'Invoice BIL-'.$billing->id,
            'reference' => 'BIL-'.$billing->id,
            'created_at' => $billing->created_at,
            'download_type' => 'invoice',
            'download_id' => $billing->id,
        ]);

        $receipts = Receipt::query()->where('unit_id', $unit->id)->latest('transaction_date')->limit(20)->get()->map(fn (Receipt $receipt) => [
            'id' => 'receipt-'.$receipt->number,
            'type' => 'receipt',
            'name' => 'Kuitansi '.$receipt->number,
            'reference' => $receipt->number,
            'created_at' => $receipt->transaction_date,
            'download_type' => 'receipt',
            'download_id' => $receipt->number,
        ]);

        $payments = PaymentTransaction::query()->where('unit_id', $unit->id)->where('status', 'paid')->latest()->limit(20)->get()->map(fn (PaymentTransaction $payment) => [
            'id' => 'payment-'.$payment->id,
            'type' => 'receipt',
            'name' => 'Receipt '.$payment->transaction_number,
            'reference' => $payment->transaction_number,
            'created_at' => $payment->paid_at ?: $payment->created_at,
            'download_type' => 'payment_receipt',
            'download_id' => $payment->id,
        ]);

        return $invoices->merge($receipts)->merge($payments)->sortByDesc('created_at')->values()->all();
    }

    private function notificationQuery(Request $request, Unit $unit): Builder
    {
        return NotificationQueue::query()
            ->where(function (Builder $q) use ($request, $unit) {
                $q->where('unit_id', $unit->id)
                    ->orWhere('user_id', $request->user()->id)
                    ->orWhere(fn (Builder $global) => $global->whereNull('unit_id')->whereNull('user_id')->where('type', 'like', 'announcement%'));
            });
    }

    private function notificationOwnedBy(NotificationQueue $notification, Request $request, Unit $unit): bool
    {
        return $notification->unit_id === $unit->id
            || $notification->user_id === $request->user()->id
            || (blank($notification->unit_id) && blank($notification->user_id) && str_starts_with($notification->type, 'announcement'));
    }

    private function activityQuery(Request $request): Builder
    {
        return AuditLog::query()
            ->where('user_id', $request->user()->id)
            ->whereNotIn('module', ['audit-logs', 'users']);
    }

    private function storeManagedFile($file, $user, $entity, string $folder, ?string $stored = null): ManagedFile
    {
        $path = $stored ?: $file->store($folder, 'public');

        return ManagedFile::query()->create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => basename($path),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
        ]);
    }
}
