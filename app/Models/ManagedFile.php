<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManagedFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'original_filename', 'stored_filename', 'path', 'disk', 'mime_type',
        'extension', 'size', 'uploaded_by', 'entity_type', 'entity_id',
    ];

    public function entity()
    {
        return $this->morphTo();
    }
}
