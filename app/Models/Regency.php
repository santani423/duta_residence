<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Regency extends Model
{
    use HasStringPrimaryKey;

    public $timestamps = false;

    protected $fillable = ['id', 'name'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
