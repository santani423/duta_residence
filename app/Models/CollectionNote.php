<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use App\Models\Concerns\HasVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Catatan internal penagihan - tidak boleh tampil di portal/endpoint resident. */
class CollectionNote extends Model
{
    use HasClientUuid, HasVersion, SoftDeletes;

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = [
        'unit_id', 'title', 'body', 'priority', 'client_uuid', 'version', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
