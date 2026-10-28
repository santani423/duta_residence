<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public const AREA_TYPES = ['cluster', 'ruko'];

    protected $fillable = ['id', 'name', 'code_prefix', 'area_type', 'allowed_blocks', 'monthly_rate', 'description', 'is_active'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'allowed_blocks' => 'array',
    ];

    /**
     * Apakah nomor blok diizinkan untuk cluster ini. allowed_blocks null = sesuai data blok
     * (tidak dibatasi); nomor blok tidak diasumsikan mulai dari 1 atau berurutan.
     */
    public function allowsBlock(int $blockNumber): bool
    {
        return $blockNumber >= 1
            && ($this->allowed_blocks === null || in_array($blockNumber, $this->allowed_blocks, true));
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function map()
    {
        return $this->hasOne(ClusterMap::class);
    }

    public function rateSchedules()
    {
        return $this->hasMany(ClusterRateSchedule::class)->orderByDesc('effective_date');
    }

    public function currentRateSchedule()
    {
        return $this->rateSchedules()->effective()->first();
    }

    public function nextRateSchedule()
    {
        return $this->rateSchedules()->upcoming()->reorder('effective_date')->first();
    }
}
