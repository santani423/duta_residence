<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Billing extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id', 'year', 'month', 'amount', 'penalty', 'discount', 'status_id',
        'is_penalty_eligible', 'is_discount_eligible', 'billing_type', 'approved_by',
        'approved_at', 'approval_notes', 'paid_at', 'receipt_number', 'loket_code',
        'processed_by', 'spt_print_count', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'penalty' => 'decimal:2',
        'discount' => 'decimal:2',
        'is_penalty_eligible' => 'boolean',
        'is_discount_eligible' => 'boolean',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function status()
    {
        return $this->belongsTo(BillingStatus::class, 'status_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_number', 'number');
    }

    public function paymentTransactions()
    {
        return $this->belongsToMany(PaymentTransaction::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status_id', '01');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }
}
