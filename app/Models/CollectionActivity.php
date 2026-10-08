<?php

namespace App\Models;

use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris timeline penagihan. Append-only: tidak ada update/hapus dari alur aplikasi.
 */
class CollectionActivity extends Model
{
    use HasClientUuid;

    public const TYPE_CALL = 'call';

    public const TYPE_WHATSAPP = 'whatsapp';

    public const TYPE_SMS = 'sms';

    public const TYPE_EMAIL = 'email';

    public const TYPE_VISIT = 'visit';

    public const TYPE_PROMISE = 'promise';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_DISPUTE = 'dispute';

    public const TYPE_ESCALATION = 'escalation';

    public const TYPE_NOTE = 'note';

    public const TYPE_ASSIGNMENT = 'assignment';

    public const TYPE_LETTER = 'letter';

    public const TYPE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_CALL, self::TYPE_WHATSAPP, self::TYPE_SMS, self::TYPE_EMAIL, self::TYPE_VISIT,
        self::TYPE_PROMISE, self::TYPE_PAYMENT, self::TYPE_DISPUTE, self::TYPE_ESCALATION,
        self::TYPE_NOTE, self::TYPE_ASSIGNMENT, self::TYPE_LETTER, self::TYPE_SYSTEM,
    ];

    /** Tipe yang dihitung sebagai "kontak" ke customer (last contact, failed contact). */
    public const CONTACT_TYPES = [self::TYPE_CALL, self::TYPE_WHATSAPP, self::TYPE_SMS, self::TYPE_EMAIL];

    public const RESULT_ANSWERED = 'answered';

    public const RESULT_NO_ANSWER = 'no_answer';

    public const RESULT_BUSY = 'busy';

    public const RESULT_WRONG_NUMBER = 'wrong_number';

    public const RESULT_CALLBACK_REQUESTED = 'callback_requested';

    public const RESULT_PROMISED_PAYMENT = 'promised_payment';

    public const RESULT_OTHER = 'other';

    public const CHANNEL_RESULTS = [
        self::RESULT_ANSWERED, self::RESULT_NO_ANSWER, self::RESULT_BUSY, self::RESULT_WRONG_NUMBER,
        self::RESULT_CALLBACK_REQUESTED, self::RESULT_PROMISED_PAYMENT, self::RESULT_OTHER,
    ];

    /** Hasil kontak yang dianggap gagal untuk skor prioritas. */
    public const FAILED_RESULTS = [self::RESULT_NO_ANSWER, self::RESULT_BUSY, self::RESULT_WRONG_NUMBER];

    protected $fillable = [
        'unit_id', 'customer_resident_id', 'collector_id', 'type', 'event', 'channel_result',
        'subject_type', 'subject_id', 'summary', 'details', 'occurred_at', 'next_follow_up_at',
        'latitude', 'longitude', 'client_uuid', 'created_by',
    ];

    protected $casts = [
        'details' => 'array',
        'occurred_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function customer()
    {
        return $this->belongsTo(Resident::class, 'customer_resident_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
