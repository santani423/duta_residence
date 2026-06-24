<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class OccupancyStatus extends Model
{
    use HasStringPrimaryKey;

    public $timestamps = false;

    protected $fillable = ['id', 'name'];
}
