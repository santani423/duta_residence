<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\CustomerComplaint;
use App\Models\CustomerComplaintComment;
use App\Models\ManagedFile;
use App\Models\MaintenanceRequest;
use App\Models\NotificationQueue;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Services\AuditService;
use App\Services\Payments\PaymentGatewayFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    use ApiResponse;

    public function dashboard(Request $request)
    {
        $customer = $this->customer($request);
        $billings = $this->billingBaseQuery($customer)->get();
        $payments = PaymentTransaction::query()->where('customer_id', $customer->id)->latest()->get();
        $activeComplaints = $customer->complaints()->whereNotIn('status', ['closed', 'resolved', 'rejected'])->count();
        $activeMaintenance = $customer->maintenanceRequests()->whereNotIn('status', ['completed', 'closed', 'rejected'])->count();

        return $this->success([
            'customer' => $this->customerProfile($customer, $request->user()),
            'estate' => [
                'name' => 'Grand Duta Residence',
                'cluster' => $customer->cluster?->name,
            ],
            'property' => $this->propertyPayload($customer),
            'billing_summary' => [
                'active_total' => $billings->where('status_id', '01')->sum(fn (Billing $billing) => $this->billingTotal($billing)),
                'unpaid_count' => $billings->where('status_id', '01')->count(),
                'overdue_count' => $billings->filter(fn (Billing $billing) => $this->invoiceStatus($billing) === 'overdue')->count(),
                'overdue_total' => $billings->filter(fn (Billing $billing) => $this->invoiceStatus($billing) === 'overdue')->sum(fn (Billing $billing) => $this->billingTotal($billing)),
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
                    ['label' => 'Dokumen tersedia', 'value' => count($this->documentsForCustomer($customer))],
                ],
            ],
            'latest_documents' => array_slice($this->documentsForCustomer($customer), 0, 5),
            'latest_notifications' => $this->notificationQuery($request, $customer)->latest()->limit(5)->get(),
            'latest_activity' => $this->activityQuery($request)->latest()->limit(8)->get(),
            'payment_config' => PaymentGatewaySetting::current()->publicConfig(),
        ]);
    }

    public function account(Request $request)
    {
        $customer = $this->customer($request);

        return $this->success([
            'account' => $this->customerProfile($customer, $request->user()),
            'property' => $this->propertyPayload($customer),
            'billings' => $this->billingBaseQuery($customer)->latest()->limit(10)->get()->map(fn (Billing $billing) => $this->invoicePayload($billing)),
            'payments' => PaymentTransaction::query()->where('customer_id', $customer->id)->latest()->limit(10)->get()->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction)),
            'payment_methods' => PaymentGatewaySetting::current()->publicConfig(),
            'documents' => $this->documentsForCustomer($customer),
            'security' => [
                'last_login_at' => $request->user()->last_login_at,
                'last_login_ip' => $request->user()->last_login_ip,
            ],
            'notifications' => $this->notificationQuery($request, $customer)->latest()->limit(10)->get(),
            'activity' => $this->activityQuery($request)->latest()->limit(10)->get(),
        ]);
    }

    public function profile(Request $request)
    {
        $customer = $this->customer($request);

        return $this->success($this->customerProfile($customer, $request->user()));
    }

    public function updateProfile(Request $request, AuditService $auditService)
    {
        $customer = $this->customer($request);
        $user = $request->user();
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
        $oldCustomer = $customer->toArray();

        $user->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $user->phone,
            'email' => $data['email'] ?? $user->email,
            'language_preference' => $data['language_preference'] ?? $user->language_preference,
            'theme_preference' => $data['theme_preference'] ?? $user->theme_preference,
            'notification_preferences' => $data['notification_preferences'] ?? $user->notification_preferences,
        ]);

        $customer->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? $customer->phone,
            'email' => $data['email'] ?? $customer->email,
            'id_card_address' => $data['id_card_address'] ?? $customer->id_card_address,
            'updated_by' => $user->id,
        ]);

        if ($request->hasFile('photo')) {
            $this->storeManagedFile($request->file('photo'), $user, $customer, 'customer-profiles');
        }

        $auditService->log('customer_profile_updated', 'customer-profile', 'UPDATE', $customer, $oldCustomer, $customer->refresh()->toArray());
        $auditService->log('customer_account_updated', 'customer-account', 'UPDATE', $user, $oldUser, $user->refresh()->toArray());

        return $this->success($this->customerProfile($customer->refresh(), $user->refresh()), 'Profil berhasil diperbarui.');
    }

    public function property(Request $request)
    {
        return $this->success($this->propertyPayload($this->customer($request)));
    }

    public function bills(Request $request)
    {
        $customer = $this->customer($request);
        $query = $this->billingBaseQuery($customer)
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('id', $value)
                ->orWhere('billing_type', 'like', "%{$value}%")))
            ->when($request->query('period'), function (Builder $q, string $period) {
                [$year, $month] = array_pad(explode('-', $period), 2, null);
                $q->where('year', $year)->when($month, fn (Builder $inner) => $inner->where('month', (int) $month));
            });

        if ($status = $request->query('status')) {
            $query = $query->get()->filter(fn (Billing $billing) => $this->invoiceStatus($billing) === $status)->values();

            return $this->success($query->map(fn (Billing $billing) => $this->invoicePayload($billing))->values());
        }

        $paginator = $query
            ->when($request->query('sort') === 'due_date', fn (Builder $q) => $q->orderBy('year')->orderBy('month'))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $paginator->setCollection($paginator->getCollection()->map(fn (Billing $billing) => $this->invoicePayload($billing)));

        return $this->paginated($paginator);
    }

    public function invoice(Request $request, Billing $billing)
    {
        $billing = $this->ownedBilling($request, $billing);

        return $this->success([
            ...$this->invoicePayload($billing),
            'customer' => $this->customerProfile($billing->customer, $request->user()),
            'estate' => ['name' => 'Grand Duta Residence'],
            'unit' => $this->propertyPayload($billing->customer),
            'payment_history' => $billing->paymentTransactions->map(fn (PaymentTransaction $transaction) => $this->paymentPayload($transaction)),
        ]);
    }

    public function downloadInvoice(Request $request, Billing $billing)
    {
        $billing = $this->ownedBilling($request, $billing);

        return Pdf::loadHTML(view('pdf.customer-invoice', compact('billing'))->render())
            ->download("Invoice-{$billing->id}.pdf");
    }

    public function paymentConfig()
    {
        return $this->success(PaymentGatewaySetting::current()->publicConfig(), 'Konfigurasi pembayaran berhasil ditemukan.');
    }

    public function createPayment(Request $request, Billing $billing, PaymentGatewayFactory $factory)
    {
        $billing = $this->ownedBilling($request, $billing);
        $setting = PaymentGatewaySetting::current();
        $data = $request->validate([
            'provider' => ['nullable', Rule::in(['manual', 'xendit', 'midtrans'])],
        ]);
        $provider = $data['provider'] ?? $setting->active_gateway;

        if (! $setting->is_active || ! in_array($provider, $setting->availableGateways(), true)) {
            throw ValidationException::withMessages(['provider' => ['Metode pembayaran sedang tidak tersedia.']]);
        }

        if ($billing->status_id !== '01' || blank($billing->approved_at)) {
            throw ValidationException::withMessages(['billing' => ['Invoice tidak dapat dibayar.']]);
        }

        $transaction = DB::transaction(function () use ($billing, $request, $setting, $provider, $factory) {
            $transaction = PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'customer_id' => $billing->customer_id,
                'subtotal' => $this->billingTotal($billing),
                'tax' => 0,
                'admin_fee' => (float) $setting->admin_fee,
                'total' => $this->billingTotal($billing) + (float) $setting->admin_fee,
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
        $customer = $this->customer($request);
        $query = PaymentTransaction::query()
            ->with('billings')
            ->where('customer_id', $customer->id)
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
            'customer' => $this->customerProfile($payment->customer, $request->user()),
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

        return Pdf::loadHTML(view('pdf.customer-payment-receipt', compact('payment'))->render())
            ->download("Receipt-{$payment->transaction_number}.pdf");
    }

    public function downloadCashReceipt(Request $request, Receipt $receipt)
    {
        $customer = $this->customer($request);
        abort_if($receipt->customer_id !== $customer->id, 404);
        $receipt->load(['customer.cluster', 'billings']);

        return Pdf::loadHTML(view('pdf.spt', compact('receipt'))->render())
            ->download("SPT-{$receipt->number}.pdf");
    }

    public function complaints(Request $request)
    {
        $customer = $this->customer($request);
        $query = $customer->complaints()->withCount('comments')
            ->when($request->query('status'), fn (Builder $q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeComplaint(Request $request, AuditService $auditService)
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:80'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('complaints', 'public') : null;
        $complaint = $customer->complaints()->create([
            ...collect($data)->except('attachment')->all(),
            'attachment_path' => $attachment,
            'user_id' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeManagedFile($request->file('attachment'), $request->user(), $complaint, 'complaints', $attachment);
        }

        $auditService->log('customer_complaint_created', 'complaints', 'CREATE', $complaint, [], $complaint->toArray());

        return $this->success($complaint->load('comments.user'), 'Komplain berhasil dibuat.', 201);
    }

    public function complaint(Request $request, CustomerComplaint $complaint)
    {
        $complaint = $this->ownedComplaint($request, $complaint);

        return $this->success($complaint->load(['comments.user', 'customer.cluster']));
    }

    public function updateComplaint(Request $request, CustomerComplaint $complaint, AuditService $auditService)
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
        $auditService->log('customer_complaint_updated', 'complaints', 'UPDATE', $complaint, $old, $complaint->refresh()->toArray());

        return $this->success($complaint->load('comments.user'), 'Komplain berhasil diperbarui.');
    }

    public function addComplaintComment(Request $request, CustomerComplaint $complaint, AuditService $auditService)
    {
        $complaint = $this->ownedComplaint($request, $complaint);
        $data = $request->validate([
            'body' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('complaints', 'public') : null;
        $comment = CustomerComplaintComment::query()->create([
            'customer_complaint_id' => $complaint->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'attachment_path' => $attachment,
            'is_staff_response' => false,
        ]);

        if ($request->hasFile('attachment')) {
            $this->storeManagedFile($request->file('attachment'), $request->user(), $comment, 'complaints', $attachment);
        }

        $auditService->log('customer_complaint_comment_added', 'complaints', 'COMMENT', $complaint, [], $comment->toArray());

        return $this->success($comment->load('user'), 'Komentar berhasil ditambahkan.', 201);
    }

    public function closeComplaint(Request $request, CustomerComplaint $complaint, AuditService $auditService)
    {
        $complaint = $this->ownedComplaint($request, $complaint);
        $old = $complaint->toArray();
        $complaint->update(['status' => 'closed', 'closed_at' => now(), 'updated_by' => $request->user()->id]);
        $auditService->log('customer_complaint_closed', 'complaints', 'CLOSE', $complaint, $old, $complaint->refresh()->toArray());

        return $this->success($complaint, 'Komplain berhasil ditutup.');
    }

    public function maintenanceRequests(Request $request)
    {
        $customer = $this->customer($request);
        $query = $customer->maintenanceRequests()
            ->when($request->query('status'), fn (Builder $q, $value) => $q->where('status', $value))
            ->when($request->query('search'), fn (Builder $q, $value) => $q->where(fn (Builder $inner) => $inner
                ->where('category', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function storeMaintenanceRequest(Request $request, AuditService $auditService)
    {
        $customer = $this->customer($request);
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string'],
            'urgency' => ['required', Rule::in(['low', 'normal', 'high', 'emergency'])],
            'preferred_schedule' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $attachment = $request->hasFile('attachment') ? $request->file('attachment')->store('maintenance', 'public') : null;
        $maintenance = $customer->maintenanceRequests()->create([
            ...collect($data)->except('attachment')->all(),
            'unit_label' => "{$customer->cluster?->name} {$customer->block}/{$customer->lot_number}",
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
        return $this->success($this->documentsForCustomer($this->customer($request)));
    }

    public function notifications(Request $request)
    {
        $customer = $this->customer($request);

        return $this->paginated(
            $this->notificationQuery($request, $customer)
                ->when($request->query('read_status'), fn (Builder $q, $value) => $q->where('read_status', $value))
                ->when($request->query('type'), fn (Builder $q, $value) => $q->where('type', $value))
                ->latest()
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function readNotification(Request $request, NotificationQueue $notification)
    {
        $customer = $this->customer($request);
        abort_if(! $this->notificationOwnedBy($notification, $request, $customer), 404);
        $notification->update(['read_status' => 'read']);

        return $this->success($notification, 'Notifikasi ditandai dibaca.');
    }

    public function readAllNotifications(Request $request)
    {
        $customer = $this->customer($request);
        $this->notificationQuery($request, $customer)->update(['read_status' => 'read']);

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
        $auditService->log('customer_settings_updated', 'customer-settings', 'UPDATE', $user, $old, $user->refresh()->toArray());

        return $this->success($this->settings($request)->getData(true)['data'], 'Pengaturan berhasil disimpan.');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()
            ->customer()
            ->with(['cluster', 'propertyType', 'occupancy', 'status', 'district'])
            ->first();

        abort_if(! $customer, 403, 'Akun customer belum terhubung dengan data pelanggan.');

        return $customer;
    }

    private function billingBaseQuery(Customer $customer): Builder
    {
        return Billing::query()
            ->with(['customer.cluster', 'paymentTransactions'])
            ->where('customer_id', $customer->id);
    }

    private function ownedBilling(Request $request, Billing $billing): Billing
    {
        $customer = $this->customer($request);
        abort_if($billing->customer_id !== $customer->id, 404);

        return $billing->load(['customer.cluster', 'customer.propertyType', 'paymentTransactions']);
    }

    private function ownedPayment(Request $request, PaymentTransaction $payment): PaymentTransaction
    {
        $customer = $this->customer($request);
        abort_if($payment->customer_id !== $customer->id, 404);

        return $payment->load(['customer.cluster', 'billings']);
    }

    private function ownedComplaint(Request $request, CustomerComplaint $complaint): CustomerComplaint
    {
        $customer = $this->customer($request);
        abort_if($complaint->customer_id !== $customer->id, 404);

        return $complaint;
    }

    private function ownedMaintenance(Request $request, MaintenanceRequest $maintenance): MaintenanceRequest
    {
        $customer = $this->customer($request);
        abort_if($maintenance->customer_id !== $customer->id, 404);

        return $maintenance;
    }

    private function customerProfile(Customer $customer, $user): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email ?: $user->email,
            'phone' => $customer->phone ?: $user->phone,
            'customer_number' => $customer->id,
            'status' => $customer->status?->name,
            'estate' => 'Grand Duta Residence',
            'unit' => "{$customer->cluster?->name} {$customer->block}/{$customer->lot_number}",
            'joined_at' => $customer->created_at,
            'last_login_at' => $user->last_login_at,
            'address' => $customer->id_card_address,
            'language_preference' => $user->language_preference,
            'theme_preference' => $user->theme_preference,
            'notification_preferences' => $user->notification_preferences,
        ];
    }

    private function propertyPayload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'estate' => 'Grand Duta Residence',
            'cluster' => $customer->cluster?->name,
            'block' => $customer->block,
            'lot_number' => $customer->lot_number,
            'unit_label' => "{$customer->cluster?->name} {$customer->block}/{$customer->lot_number}",
            'property_type' => $customer->propertyType?->name,
            'occupancy' => $customer->occupancy?->name,
            'building_area' => $customer->building_area,
            'land_area' => $customer->land_area,
            'handover_date' => $customer->handover_date,
            'status' => $customer->status?->name,
        ];
    }

    private function invoicePayload(Billing $billing): array
    {
        return [
            'id' => $billing->id,
            'invoice_number' => 'BIL-'.$billing->id,
            'billing_type' => $billing->billing_type,
            'period' => sprintf('%04d-%02d', $billing->year, $billing->month),
            'year' => $billing->year,
            'month' => $billing->month,
            'issued_at' => $billing->created_at,
            'due_date' => $this->dueDate($billing),
            'subtotal' => (float) $billing->amount,
            'tax' => 0,
            'admin_fee' => 0,
            'discount' => (float) $billing->discount,
            'penalty' => (float) $billing->penalty,
            'total' => $this->billingTotal($billing),
            'total_paid' => $billing->status_id === '02' ? $this->billingTotal($billing) : 0,
            'status' => $this->invoiceStatus($billing),
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

    private function invoiceStatus(Billing $billing): string
    {
        if ($billing->status_id === '02') {
            return 'paid';
        }

        if (blank($billing->approved_at)) {
            return 'pending';
        }

        return now()->greaterThan($this->dueDate($billing)) ? 'overdue' : 'unpaid';
    }

    private function dueDate(Billing $billing)
    {
        $day = min((int) config('grandduta.notification.penalty_day', 20), 28);

        return now()->setDate((int) $billing->year, (int) $billing->month, $day)->startOfDay();
    }

    private function billingTotal(Billing $billing): float
    {
        return (float) $billing->amount + (float) $billing->penalty - (float) $billing->discount;
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

    private function documentsForCustomer(Customer $customer): array
    {
        $invoices = $this->billingBaseQuery($customer)->latest()->limit(20)->get()->map(fn (Billing $billing) => [
            'id' => 'invoice-'.$billing->id,
            'type' => 'invoice',
            'name' => 'Invoice BIL-'.$billing->id,
            'reference' => 'BIL-'.$billing->id,
            'created_at' => $billing->created_at,
            'download_type' => 'invoice',
            'download_id' => $billing->id,
        ]);

        $receipts = Receipt::query()->where('customer_id', $customer->id)->latest('transaction_date')->limit(20)->get()->map(fn (Receipt $receipt) => [
            'id' => 'receipt-'.$receipt->number,
            'type' => 'receipt',
            'name' => 'Kuitansi '.$receipt->number,
            'reference' => $receipt->number,
            'created_at' => $receipt->transaction_date,
            'download_type' => 'receipt',
            'download_id' => $receipt->number,
        ]);

        $payments = PaymentTransaction::query()->where('customer_id', $customer->id)->where('status', 'paid')->latest()->limit(20)->get()->map(fn (PaymentTransaction $payment) => [
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

    private function notificationQuery(Request $request, Customer $customer): Builder
    {
        return NotificationQueue::query()
            ->where(function (Builder $q) use ($request, $customer) {
                $q->where('customer_id', $customer->id)
                    ->orWhere('user_id', $request->user()->id)
                    ->orWhere(fn (Builder $global) => $global->whereNull('customer_id')->whereNull('user_id')->where('type', 'like', 'announcement%'));
            });
    }

    private function notificationOwnedBy(NotificationQueue $notification, Request $request, Customer $customer): bool
    {
        return $notification->customer_id === $customer->id
            || $notification->user_id === $request->user()->id
            || (blank($notification->customer_id) && blank($notification->user_id) && str_starts_with($notification->type, 'announcement'));
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
