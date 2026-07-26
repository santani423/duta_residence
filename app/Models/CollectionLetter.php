<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectionLetter extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_REMINDER = 'reminder';

    public const TYPE_WARNING = 'warning';

    public const TYPE_FINAL_NOTICE = 'final_notice';

    protected $fillable = [
        'unit_id', 'resident_id', 'billing_id', 'letter_type', 'content', 'pdf_path',
        'generated_by', 'generated_at',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function billing()
    {
        return $this->belongsTo(Billing::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
