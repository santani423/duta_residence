<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountSetting extends Model
{
    public const DEFAULT_MAXIMUM_ADMIN_DISCOUNT = 30;

    protected $fillable = ['maximum_admin_discount', 'updated_by'];

    protected $casts = [
        'maximum_admin_discount' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'maximum_admin_discount' => self::DEFAULT_MAXIMUM_ADMIN_DISCOUNT,
        ]);
    }

    public static function maximumAdminDiscount(): float
    {
        return (float) static::current()->maximum_admin_discount;
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
