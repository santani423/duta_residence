<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollectorVisit extends Model
{
    use HasFactory, SoftDeletes;

    /** Hasil kunjungan (kolom `status`) "Selesai" - baru final setelah penghuni tanda tangan. */
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [self::STATUS_COMPLETED, 'no_answer', 'refused', 'rescheduled'];

    public const LIFECYCLE_SCHEDULED = 'scheduled';

    public const LIFECYCLE_IN_PROGRESS = 'in_progress';

    public const LIFECYCLE_COMPLETED = 'completed';

    public const LIFECYCLE_FAILED = 'failed';

    public const LIFECYCLE_CANCELLED = 'cancelled';

    /** Status lama (kolom `status`) yang berarti kunjungan tidak berhasil bertemu/menagih. */
    public const FAILED_STATUSES = ['no_answer', 'refused'];

    /** result_code baru yang berarti kunjungan gagal. */
    public const FAILED_RESULT_CODES = ['not_home', 'refused', 'address_not_found'];

    protected $fillable = [
        'unit_id', 'collector_id', 'visit_date', 'purpose', 'result', 'met_with',
        'notes', 'checkin_latitude', 'checkin_longitude', 'status', 'next_visit_date',
        'created_by', 'scheduled_date', 'scheduled_time', 'priority', 'lifecycle', 'result_code',
        'started_at', 'finished_at', 'start_latitude', 'start_longitude', 'client_uuid', 'version',
        'updated_by',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
        'next_visit_date' => 'date',
        'checkin_latitude' => 'decimal:7',
        'checkin_longitude' => 'decimal:7',
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'start_latitude' => 'decimal:7',
        'start_longitude' => 'decimal:7',
        'version' => 'integer',
    ];

    protected $appends = ['awaiting_signature'];

    /**
     * "Menunggu tanda tangan": kunjungan Selesai yang sudah disimpan tetapi belum ditandatangani
     * penghuni di HP collector (lifecycle masih in_progress). Belum dihitung sebagai kunjungan
     * selesai di mana pun. Data lama (lifecycle completed) tidak pernah menunggu.
     */
    public function isAwaitingSignature(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->lifecycle === self::LIFECYCLE_IN_PROGRESS;
    }

    protected function awaitingSignature(): Attribute
    {
        return Attribute::get(fn (): bool => $this->isAwaitingSignature());
    }

    /**
     * Hanya kunjungan final (lifecycle completed/failed), sama dengan metrik kinerja: kunjungan
     * "Menunggu tanda tangan" (in_progress) belum dihitung di KPI kunjungan.
     */
    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereIn('lifecycle', [self::LIFECYCLE_COMPLETED, self::LIFECYCLE_FAILED]);
    }

    /** Tambahkan `has_signature` (ada tanda tangan penghuni yang belum dihapus) ke query. */
    public function scopeWithSignatureState(Builder $query): Builder
    {
        return $query->withExists(self::signatureExistence());
    }

    /** Versi satu model dari withSignatureState(), untuk respons simpan/ubah/unggah bukti. */
    public function loadSignatureState(): static
    {
        return $this->loadExists(self::signatureExistence());
    }

    private static function signatureExistence(): array
    {
        return ['evidence as has_signature' => fn ($q) => $q->where('type', CollectorVisitEvidence::TYPE_SIGNATURE)];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidence()
    {
        return $this->hasMany(CollectorVisitEvidence::class, 'visit_id');
    }
}
