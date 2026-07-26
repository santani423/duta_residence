<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'unit_id',
        'resident_id',
        'password',
        'is_active',
        'theme_preference',
        'language_preference',
        'notification_preferences',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function collectorAssignments()
    {
        return $this->hasMany(CollectorAssignment::class, 'collector_id');
    }

    public function collectorVisits()
    {
        return $this->hasMany(CollectorVisit::class, 'collector_id');
    }

    public function collectorLocations()
    {
        return $this->hasMany(CollectorLocation::class, 'collector_id');
    }

    public function collectorTargets()
    {
        return $this->hasMany(CollectorTarget::class, 'collector_id');
    }

    public function collectorProfile()
    {
        return $this->hasOne(CollectorProfile::class);
    }
}
