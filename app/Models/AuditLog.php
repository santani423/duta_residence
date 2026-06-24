<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'activity', 'module', 'action',
        'http_method', 'endpoint', 'entity_type', 'entity_id', 'old_data',
        'new_data', 'changed_fields', 'ip_address', 'user_agent', 'request_id',
        'status', 'description',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'changed_fields' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
