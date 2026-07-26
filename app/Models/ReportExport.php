<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportExport extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type', 'format', 'status', 'filters', 'file_path', 'row_count',
        'failed_reason', 'requested_by', 'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
