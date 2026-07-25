<?php

namespace App\Models;

use App\Models\Concerns\HasOrder;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingFaq extends Model
{
    use HasFactory, HasOrder, Publishable;

    protected $fillable = ['question', 'answer', 'category_id', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(LandingContentCategory::class, 'category_id');
    }
}
