<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reversal extends Model
{
    protected $fillable = [
        'receipt_number', 'reason', 'status', 'submitted_by', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_number', 'number');
    }
}
