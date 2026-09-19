<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSchemeItem extends Model
{
    protected $fillable = [
        'payment_scheme_id', 'billing_id', 'original_principal', 'principal_discount', 'final_principal',
        'original_penalty', 'penalty_reduction', 'final_penalty', 'previous_discount',
    ];

    protected $casts = [
        'original_principal' => 'decimal:2',
        'principal_discount' => 'decimal:2',
        'final_principal' => 'decimal:2',
        'original_penalty' => 'decimal:2',
        'penalty_reduction' => 'decimal:2',
        'final_penalty' => 'decimal:2',
        'previous_discount' => 'decimal:2',
    ];

    public function scheme()
    {
        return $this->belongsTo(PaymentScheme::class, 'payment_scheme_id');
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }
}
