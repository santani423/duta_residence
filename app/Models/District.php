<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasStringPrimaryKey;

    public $timestamps = false;

    protected $fillable = ['id', 'regency_id', 'name'];

    public function regency()
    {
        return $this->belongsTo(Regency::class);
    }
}
