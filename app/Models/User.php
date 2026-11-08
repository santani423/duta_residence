<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
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

    /**
     * Overrides the framework default so the emailed link points at the SPA's
     * /reset-password route instead of a `password.reset` named route this
     * API-only app never defines.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token, $this->email));
    }

    /**
     * Keluarkan akun dari semua perangkat: hapus semua token Sanctum dan lepas penanda sesi
     * tunggal. Dipakai saat akun staff dinonaktifkan, dihapus, atau username-nya diganti.
     */
    public function revokeApiTokens(): void
    {
        $this->tokens()->delete();

        if ($this->active_token_id !== null) {
            $this->forceFill(['active_token_id' => null])->saveQuietly();
        }
    }

    /**
     * Mulai sesi login baru dan jadikan token ini satu-satunya sesi aktif akun. Token lama tidak
     * dihapus di sini: ia tetap ada sampai kedaluwarsa, tetapi tidak lagi cocok dengan
     * `active_token_id`, sehingga ditolak middleware `single-session` dengan SESSION_REVOKED.
     *
     * Baris user dikunci (SELECT ... FOR UPDATE) supaya dua login yang hampir bersamaan berjalan
     * berurutan: login kedua menggantikan login pertama, dan tidak mungkin ada dua sesi aktif.
     *
     * @param  array<int, string>  $abilities
     * @param  array<string, mixed>  $attributes  kolom user yang disimpan dalam transaksi yang sama
     */
    public function startSingleSession(array $abilities, CarbonInterface $expiresAt, array $attributes = []): NewAccessToken
    {
        return DB::transaction(function () use ($abilities, $expiresAt, $attributes) {
            $account = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $newToken = $account->createToken('api-token', $abilities, $expiresAt);

            // Bersihkan token yang sudah kedaluwarsa supaya tabel tidak menumpuk sesi lama.
            $account->tokens()->where('expires_at', '<', now())->delete();

            $account->forceFill(array_merge($attributes, [
                'active_token_id' => $newToken->accessToken->getKey(),
            ]))->save();

            return $newToken;
        });
    }

    /** Apakah token ini sesi aktif akun. Token lain milik akun yang sama adalah sesi lama. */
    public function hasActiveSession(PersonalAccessToken $token): bool
    {
        return $this->active_token_id !== null
            && (int) $this->active_token_id === (int) $token->getKey();
    }

    /**
     * Akhiri sesi aktif (logout manual). Sesi perangkat lain tidak ikut aktif kembali: setelah ini
     * tidak ada token yang cocok dengan `active_token_id`.
     */
    public function endActiveSession(): void
    {
        $token = $this->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($this->active_token_id !== null) {
            $this->forceFill(['active_token_id' => null])->saveQuietly();
        }
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

    public function supervisorProfile()
    {
        return $this->hasOne(SupervisorProfile::class);
    }

    public function supervisorAssignments()
    {
        return $this->hasMany(SupervisorAssignment::class, 'supervisor_id');
    }
}
