<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasFactory, HasStringPrimaryKey;

    protected $fillable = ['id', 'name', 'monthly_rate', 'description', 'is_active'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function units()
    {
        return $this->hasMany(Unit::class);
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
