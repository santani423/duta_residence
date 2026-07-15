<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuidedTourStep extends Model
{
    protected $fillable = ['guided_tour_id', 'target', 'title', 'content', 'placement', 'order'];

    public function tour()
    {
        return $this->belongsTo(GuidedTour::class, 'guided_tour_id');
    }
}
