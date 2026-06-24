<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\ManagedFile;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentGatewayController extends Controller
{
    use ApiResponse;

    public function config()
    {
        return $this->success([
            'active_gateway' => config('payment.gateway', 'manual'),
            'currency' => config('payment.currency', 'IDR'),
            'manual_payment' => [
                'enabled' => config('payment.manual.enabled', true),
                'bank_name' => config('payment.manual.bank_name'),
                'account_number' => config('payment.manual.account_number'),
                'account_name' => config('payment.manual.account_name'),
            ],
            'providers' => [
                'manual' => true,
                'xendit' => filled(config('payment.xendit.secret_key')),
                'midtrans' => filled(config('payment.midtrans.server_key')),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $query = PaymentTransaction::query()
            ->with(['customer.cluster', 'billings'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('transaction_number', 'like', "%{$value}%")
                ->orWhere('invoice_number', 'like', "%{$value}%")
                ->orWhere('provider_reference', 'like', "%{$value}%")
                ->orWhere('customer_id', 'like', "%{$value}%")))
            ->when($request->query('provider'), fn ($q, $value) => $q->where('payment_provider', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('customer_id'), fn ($q, $value) => $q->where('customer_id', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(PaymentTransaction $transaction)
    {
        return $this->success($transaction->load(['customer.cluster', 'billings']));
    }

    public function create(Request $request, PaymentGatewayFactory $factory)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'provider' => ['nullable', 'in:manual,xendit,midtrans'],
        ]);

        $transaction = DB::transaction(function () use ($data, $request, $factory) {
            $billings = Billing::query()
                ->whereIn('id', $data['billing_ids'])
                ->where('customer_id', $data['customer_id'])
                ->unpaid()
                ->approved()
                ->lockForUpdate()
                ->get();

            abort_if($billings->count() !== count(array_unique($data['billing_ids'])), 422, 'Tagihan tidak valid untuk pembayaran.');

            $provider = $data['provider'] ?? config('payment.gateway', 'manual');
            $subtotal = $billings->sum('amount');

            $transaction = PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'customer_id' => $data['customer_id'],
                'subtotal' => $subtotal,
                'tax' => 0,
                'admin_fee' => 0,
                'total' => $subtotal,
                'currency' => config('payment.currency', 'IDR'),
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
        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.config('grandduta.max_upload_size', 5120)],
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
            'manual_transfer_date' => $data['manual_transfer_date'],
            'manual_notes' => $data['manual_notes'] ?? null,
            'status' => 'waiting_verification',
        ]);

        return $this->success($transaction->refresh(), 'Bukti pembayaran berhasil diunggah.');
    }

    public function verifyManual(Request $request, PaymentTransaction $transaction)
    {
        $data = $request->validate(['verification_notes' => ['nullable', 'string']]);
        $transaction->update([
            'status' => 'paid',
            'paid_at' => now(),
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => $data['verification_notes'] ?? null,
        ]);

        return $this->success($transaction->refresh(), 'Pembayaran manual berhasil diverifikasi.');
    }

    public function rejectManual(Request $request, PaymentTransaction $transaction)
    {
        $data = $request->validate(['verification_notes' => ['required', 'string']]);
        $transaction->update([
            'status' => 'rejected',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_notes' => $data['verification_notes'],
        ]);

        return $this->success($transaction->refresh(), 'Pembayaran manual berhasil ditolak.');
    }

    public function xenditWebhook(Request $request, PaymentGatewayFactory $factory)
    {
        return $this->handleWebhook('xendit', $request, $factory);
    }

    public function midtransWebhook(Request $request, PaymentGatewayFactory $factory)
    {
        return $this->handleWebhook('midtrans', $request, $factory);
    }

    private function handleWebhook(string $provider, Request $request, PaymentGatewayFactory $factory)
    {
        $eventId = $request->input('id') ?? $request->input('order_id') ?? hash('sha256', $request->getContent());

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            ['provider_reference' => $request->input('id') ?? $request->input('order_id'), 'payload' => $request->all()]
        );

        if (! $event->wasRecentlyCreated && $event->status === 'processed') {
            return $this->success(['duplicate' => true], 'Webhook sudah diproses.');
        }

        $transaction = DB::transaction(fn () => $factory->make($provider)->handleWebhook($request));
        $event->update(['status' => 'processed', 'provider_reference' => $transaction->provider_reference]);

        return $this->success($transaction, 'Webhook berhasil diproses.');
    }
}
