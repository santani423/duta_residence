<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltyWaiver extends Model
{
    protected $fillable = [
        'billing_id', 'original_penalty_amount', 'waived_penalty_amount', 'final_penalty_amount',
        'reason', 'status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'review_notes',
    ];

    protected $casts = [
        'original_penalty_amount' => 'decimal:2',
        'waived_penalty_amount' => 'decimal:2',
        'final_penalty_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
