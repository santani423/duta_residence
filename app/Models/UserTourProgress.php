<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTourProgress extends Model
{
    protected $table = 'user_tour_progress';

    protected $fillable = ['user_id', 'guided_tour_id', 'status', 'last_step', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tour()
    {
        return $this->belongsTo(GuidedTour::class, 'guided_tour_id');
    }
}
