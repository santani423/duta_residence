<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionSyncLog extends Model
{
    public const RESULT_APPLIED = 'applied';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_CONFLICT = 'conflict';

    public const RESULT_REJECTED = 'rejected';

    protected $fillable = [
        'collector_id', 'device_id', 'client_uuid', 'entity_type', 'operation', 'payload_hash',
        'result', 'server_entity_id', 'message', 'client_created_at', 'received_at',
    ];

    protected $casts = [
        'client_created_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }
}
