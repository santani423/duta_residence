<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasStringPrimaryKey;

    protected $fillable = ['id', 'name', 'monthly_rate', 'description', 'is_active'];

    protected $casts = [
        'monthly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
