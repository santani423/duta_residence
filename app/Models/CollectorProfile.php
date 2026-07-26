<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectorProfile extends Model
{
    use HasFactory;

    public const EMPLOYMENT_PERMANENT = 'tetap';

    public const EMPLOYMENT_CONTRACT = 'kontrak';

    public const EMPLOYMENT_DAILY = 'harian';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_LEAVE = 'leave';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id', 'collector_code', 'whatsapp_number', 'address', 'joined_at',
        'employment_status', 'account_status', 'working_area_notes', 'admin_notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'joined_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function photos()
    {
        return $this->morphMany(ManagedFile::class, 'entity')->latest();
    }

    public function currentPhoto()
    {
        return $this->photos()->first();
    }
}
