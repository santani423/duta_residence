<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingContentCategory extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['name', 'slug', 'group', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeGroup($query, string $group)
    {
        return $query->where('group', $group);
    }
}
