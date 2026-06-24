<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number', 'invoice_number', 'customer_id', 'subtotal', 'tax',
        'admin_fee', 'total', 'currency', 'payment_provider', 'payment_method',
        'provider_reference', 'status', 'payment_url', 'expired_at', 'paid_at',
        'manual_proof_path', 'manual_transfer_date', 'manual_notes',
        'verification_notes', 'verified_by', 'verified_at', 'provider_payload', 'created_by',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'expired_at' => 'datetime',
        'paid_at' => 'datetime',
        'manual_transfer_date' => 'date',
        'verified_at' => 'datetime',
        'provider_payload' => 'array',
    ];

    public function billings()
    {
        return $this->belongsToMany(Billing::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
