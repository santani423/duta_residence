<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectorTarget extends Model
{
    use HasFactory;

    public const PERIOD_DAILY = 'daily';

    public const PERIOD_WEEKLY = 'weekly';

    public const PERIOD_MONTHLY = 'monthly';

    protected $fillable = [
        'collector_id', 'period_type', 'period_start', 'target_amount',
        'target_visit_count', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'target_amount' => 'decimal:2',
    ];

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
