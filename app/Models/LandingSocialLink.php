<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingSocialLink extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['platform', 'url', 'icon', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
