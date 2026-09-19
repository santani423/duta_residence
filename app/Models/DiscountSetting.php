<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountSetting extends Model
{
    public const DEFAULT_MAXIMUM_ADMIN_DISCOUNT = 30;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_NOMINAL = 'nominal';

    protected $fillable = ['maximum_admin_discount', 'admin_discount_type', 'updated_by'];

    protected $casts = [
        'maximum_admin_discount' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'maximum_admin_discount' => self::DEFAULT_MAXIMUM_ADMIN_DISCOUNT,
            'admin_discount_type' => self::TYPE_PERCENTAGE,
        ]);
    }

    public static function maximumAdminDiscount(): float
    {
        return (float) static::current()->maximum_admin_discount;
    }

    public static function adminDiscountType(): string
    {
        return static::current()->admin_discount_type ?: self::TYPE_PERCENTAGE;
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
