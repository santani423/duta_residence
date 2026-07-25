<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NavMenuItem extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['label', 'url', 'open_in_new_tab', 'show_in_footer', 'order', 'is_active'];

    protected $casts = [
        'open_in_new_tab' => 'boolean',
        'show_in_footer' => 'boolean',
        'is_active' => 'boolean',
    ];
}
