<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenaltySetting extends Model
{
    public const ALLOCATION_PENALTY_FIRST = 'penalty_first';

    public const ALLOCATION_PRINCIPAL_FIRST = 'principal_first';

    protected $fillable = ['is_active', 'allocation_order', 'updated_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'is_active' => true,
            'allocation_order' => self::ALLOCATION_PENALTY_FIRST,
        ]);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
