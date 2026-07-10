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
}
