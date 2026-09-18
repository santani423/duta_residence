<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\NotificationPresenter;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\ManagedFile;
use App\Models\NotificationQueue;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Services\AuditService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentGatewayController extends Controller
{
    use ApiResponse;

    public function config()
    {
        return $this->success(PaymentGatewaySetting::current()->publicConfig());
    }

    public function index(Request $request)
    {
        $query = PaymentTransaction::query()
            ->with(['unit.cluster', 'unit.resident', 'billings', 'verifier'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('transaction_number', 'like', "%{$value}%")
                ->orWhere('invoice_number', 'like', "%{$value}%")
                ->orWhere('provider_reference', 'like', "%{$value}%")
                ->orWhere('unit_id', 'like', "%{$value}%")
                ->orWhereHas('unit', fn ($u) => $u
                    ->where('block', 'like', "%{$value}%")
                    ->orWhere('lot_number', 'like', "%{$value}%")
                    ->orWhereHas('cluster', fn ($c) => $c->where('name', 'like', "%{$value}%"))
                    ->orWhereHas('resident', fn ($r) => $r->where('name', 'like', "%{$value}%")))))
            ->when($request->query('address'), fn ($q, $value) => $q->whereHas('unit', fn ($u) => $u
                ->where('block', 'like', "%{$value}%")
                ->orWhere('lot_number', 'like', "%{$value}%")
                ->orWhereHas('cluster', fn ($c) => $c->where('name', 'like', "%{$value}%"))))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($u) => $u->where('cluster_id', $value)))
            ->when($request->query('customer'), fn ($q, $value) => $q->whereHas('unit.resident', fn ($r) => $r->where('name', 'like', "%{$value}%")))
            ->when($request->query('provider'), fn ($q, $value) => $q->where('payment_provider', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        // Most recently touched first (proof uploaded, verified, rejected, paid ...), not
        // most recently created - so a transaction that just got a new proof jumps to the top.
        return $this->paginated($query->orderByDesc('updated_at')->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function show(PaymentTransaction $transaction)
    {
        return $this->success($transaction->load(['unit.cluster', 'unit.resident', 'billings', 'verifier']));
    }

    public function create(Request $request, PaymentGatewayFactory $factory, PenaltyService $penaltyService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'provider' => ['nullable', 'in:manual,xendit,midtrans'],
        ]);

        $setting = PaymentGatewaySetting::current();
        $provider = $data['provider'] ?? $setting->active_gateway;

        if (! $setting->is_active || ! in_array($provider, $setting->availableGateways(), true)) {
            throw ValidationException::withMessages(['provider' => ['Metode pembayaran sedang tidak tersedia.']]);
        }

        $transaction = DB::transaction(function () use ($data, $request, $factory, $setting, $provider, $penaltyService) {
            $billings = Billing::query()
                ->whereIn('id', $data['billing_ids'])
                ->where('unit_id', $data['unit_id'])
                ->outstanding()
                ->approved()
                ->lockForUpdate()
                ->get();

            abort_if($billings->count() !== count(array_unique($data['billing_ids'])), 422, 'Tagihan tidak valid untuk pembayaran.');

            $calculations = $billings->map(fn (Billing $billing) => $penaltyService->calculateInvoiceTotal($billing));
            $subtotal = $calculations->sum('outstanding_principal');
            $penaltyTotal = $calculations->sum('outstanding_penalty');
            $adminFee = (float) $setting->admin_fee;

            $transaction = PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'unit_id' => $data['unit_id'],
                'subtotal' => $subtotal,
                'tax' => 0,
                'admin_fee' => $adminFee,
                'total' => $subtotal + $penaltyTotal + $adminFee,
                'currency' => $setting->currency,
                'payment_provider' => $provider,
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);
            $transaction->billings()->sync($billings->pluck('id'));

            return $factory->make($provider)->create($transaction);
        });

        return $this->success($transaction->load('billings'), 'Transaksi pembayaran berhasil dibuat.', 201);
    }

    public function uploadManualProof(Request $request, PaymentTransaction $transaction)
    {
        $setting = PaymentGatewaySetting::current();
        $extensions = implode(',', $setting->proof_allowed_extensions ?: ['jpg', 'jpeg', 'png', 'pdf']);
        $data = $request->validate([
            'proof' => ['required', 'file', "mimes:{$extensions}", 'max:'.$setting->proof_max_size_kb],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'manual_transfer_date' => ['required', 'date'],
            'manual_notes' => ['nullable', 'string'],
        ]);

        $file = $data['proof'];
        $stored = $file->store('manual-payments', 'public');

        ManagedFile::query()->create([
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $transaction->id,
        ]);

        $transaction->update([
            'manual_proof_path' => $stored,
            'manual_amount' => $data['amount'] ?? $transaction->manual_amount,
            'manual_transfer_date' => $data['manual_transfer_date'],
            'manual_notes' => $data['manual_notes'] ?? null,
            'manual_proof_uploaded_at' => now(),
            'status' => 'waiting_verification',
            'verification_notes' => null,
        ]);

        return $this->success($transaction->refresh(), 'Bukti pembayaran berhasil diunggah.');
    }

    public function verifyManual(Request $request, PaymentTransaction $transaction, AuditService $auditService, PaymentService $paymentService)
    {
        $data = $request->validate(['verification_notes' => ['nullable', 'string']]);
        DB::transaction(function () use ($transaction, $request, $data, $auditService, $paymentService) {
            $old = $transaction->toArray();
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'verification_notes' => $data['verification_notes'] ?? null,
            ]);
            $paymentService->settleGatewayTransaction($transaction->refresh());
            $auditService->log('payment_manual_verified', 'payments', 'VERIFY', $transaction, $old, $transaction->toArray());

            NotificationQueue::query()->create([
                'unit_id' => $transaction->unit_id,
                'user_id' => null,
                'type' => 'payment_verified',
                'sender_id' => $request->user()->id,
                ...NotificationPresenter::referenceFor($transaction),
                'channel' => 'in_app',
                'recipient' => $transaction->unit?->resident?->phone ?: ($transaction->unit?->resident?->email ?: $transaction->unit_id),
                'message' => 'Pembayaran Anda telah diverifikasi dan tagihan dinyatakan lunas.',
                'read_status' => 'unread',
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        });

        return $this->success($transaction->refresh(), 'Pembayaran manual berhasil diverifikasi.');
    }

    public function rejectManual(Request $request, PaymentTransaction $transaction, AuditService $auditService)
    {
        $data = $request->validate(['verification_notes' => ['required', 'string']]);
        $old = $transaction->toArray();
        $transaction->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => $data['verification_notes'],
        ]);
        $auditService->log('payment_manual_rejected', 'payments', 'REJECT', $transaction, $old, $transaction->refresh()->toArray());

        NotificationQueue::query()->create([
            'unit_id' => $transaction->unit_id,
            'user_id' => null,
            'type' => 'payment_rejected',
            'sender_id' => $request->user()->id,
            ...NotificationPresenter::referenceFor($transaction),
            'channel' => 'in_app',
            'recipient' => $transaction->unit?->resident?->phone ?: ($transaction->unit?->resident?->email ?: $transaction->unit_id),
            'message' => "Bukti pembayaran Anda ditolak. Alasan: {$data['verification_notes']}. Silakan periksa alasan penolakan dan upload kembali bukti pembayaran.",
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return $this->success($transaction->refresh(), 'Pembayaran manual berhasil ditolak.');
    }

    public function xenditWebhook(Request $request, PaymentGatewayFactory $factory, PaymentService $paymentService)
    {
        return $this->handleWebhook('xendit', $request, $factory, $paymentService);
    }

    public function midtransWebhook(Request $request, PaymentGatewayFactory $factory, PaymentService $paymentService)
    {
        return $this->handleWebhook('midtrans', $request, $factory, $paymentService);
    }

    private function handleWebhook(string $provider, Request $request, PaymentGatewayFactory $factory, PaymentService $paymentService)
    {
        $eventId = $request->input('id') ?? $request->input('order_id') ?? hash('sha256', $request->getContent());

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            ['provider_reference' => $request->input('id') ?? $request->input('order_id'), 'payload' => $request->all()]
        );

        if (! $event->wasRecentlyCreated && $event->status === 'processed') {
            return $this->success(['duplicate' => true], 'Webhook sudah diproses.');
        }

        $transaction = DB::transaction(function () use ($provider, $request, $factory, $paymentService) {
            $transaction = $factory->make($provider)->handleWebhook($request);

            if ($transaction->status === 'paid') {
                $paymentService->settleGatewayTransaction($transaction);
            }

            return $transaction;
        });
        $event->update(['status' => 'processed', 'provider_reference' => $transaction->provider_reference]);

        return $this->success($transaction, 'Webhook berhasil diproses.');
    }
}
