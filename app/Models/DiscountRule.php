<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountRule extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    protected $fillable = ['name', 'type', 'value', 'is_active', 'created_by', 'updated_by'];

    protected $casts = [
        'value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * Nominal diskon untuk suatu tagihan, dibatasi supaya tidak pernah melebihi pokok
     * tagihannya sendiri (pokok tidak boleh menjadi negatif).
     */
    public function computeDiscount(float $principalAmount): float
    {
        $raw = $this->type === self::TYPE_PERCENTAGE
            ? $principalAmount * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min(max(0.0, $raw), $principalAmount), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
