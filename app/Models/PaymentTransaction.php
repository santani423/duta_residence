<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    /** Set automatically whenever a payment proof is uploaded; never chosen by the payer. */
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    private const METHOD_LABELS = [
        self::METHOD_BANK_TRANSFER => 'Transfer',
        'xendit_invoice' => 'Xendit Invoice',
        'snap' => 'Midtrans Snap',
        'gateway' => 'Gateway',
    ];

    private const STATUS_LABELS = [
        'pending' => 'Pending',
        'waiting_verification' => 'Menunggu Verifikasi',
        'paid' => 'Berhasil',
        'failed' => 'Gagal',
        'expired' => 'Kedaluwarsa',
        'cancelled' => 'Dibatalkan',
        'rejected' => 'Ditolak',
    ];

    protected $fillable = [
        'transaction_number', 'invoice_number', 'unit_id', 'subtotal', 'tax',
        'admin_fee', 'total', 'currency', 'payment_provider', 'payment_method',
        'provider_reference', 'status', 'payment_url', 'expired_at', 'paid_at',
        'manual_proof_path', 'manual_sender_name', 'manual_sender_bank',
        'manual_sender_account_number', 'manual_amount', 'manual_transfer_date',
        'manual_notes', 'manual_proof_uploaded_at',
        'verification_notes', 'verified_by', 'verified_at', 'provider_payload', 'created_by',
    ];

    protected $appends = ['payment_method_label'];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'manual_amount' => 'decimal:2',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'manual_transfer_date' => 'date',
        'manual_proof_uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return $this->payment_method ? (self::METHOD_LABELS[$this->payment_method] ?? $this->payment_method) : null;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function billings()
    {
        return $this->belongsToMany(Billing::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
