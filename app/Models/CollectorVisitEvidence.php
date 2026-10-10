<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $appends = ['file_url'];

    /**
     * Path API relatif (tanpa base URL) untuk berkas bukti, diambil klien dengan bearer token-nya
     * lewat GET /api/v1/visit-evidence/{id}/file. Null untuk GPS atau bukti tanpa berkas.
     */
    protected function fileUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->type !== self::TYPE_GPS && filled($this->file_path)
            ? "visit-evidence/{$this->id}/file"
            : null);
    }

    public function visit()
    {
        return $this->belongsTo(CollectorVisit::class, 'visit_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
