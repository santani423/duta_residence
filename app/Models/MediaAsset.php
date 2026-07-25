<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'mime_type', 'width', 'height', 'size', 'variants',
        'alt_text', 'uploaded_by', 'entity_type', 'entity_id',
    ];

    protected $casts = [
        'variants' => 'array',
    ];

    // Deliberately no absolute-URL accessor here: every other file reference
    // in this app (ManagedFile paths, cluster-map backgrounds, ...) is stored
    // and serialized as a disk-relative path, with the frontend building the
    // full URL via storageUrl() from its own configured API base URL. Doing
    // the same for `path` and `variants` here (rather than using Laravel's
    // asset()/APP_URL on the backend) avoids a mismatch when APP_URL and the
    // frontend's API base URL point at different hosts (e.g. prod domain).

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
