<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualBookRead extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'manual_book_section_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function section()
    {
        return $this->belongsTo(ManualBookSection::class, 'manual_book_section_id');
    }
}
