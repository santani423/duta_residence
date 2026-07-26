<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectorVisitEvidence extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PHOTO = 'photo';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_GPS = 'gps';

    public const TYPE_SIGNATURE = 'signature';

    protected $fillable = [
        'visit_id', 'type', 'file_path', 'latitude', 'longitude', 'captured_at', 'uploaded_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'captured_at' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(CollectorVisit::class, 'visit_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
