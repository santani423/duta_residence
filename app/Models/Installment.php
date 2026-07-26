<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    protected $fillable = ['unit_id', 'installment_plan_id', 'amount', 'payment_date', 'notes', 'allocated_to', 'created_by'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function installmentPlan()
    {
        return $this->belongsTo(InstallmentPlan::class);
    }
}
