<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResidentDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'documentable_type', 'documentable_id', 'document_type', 'document_number',
        'document_date', 'valid_from', 'valid_until', 'file_path', 'original_filename',
        'verification_status', 'verified_by', 'verified_at', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'verified_at' => 'datetime',
    ];

    public function documentable()
    {
        return $this->morphTo();
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
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
