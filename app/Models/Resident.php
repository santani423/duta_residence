<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    protected $fillable = [
        'id', 'name', 'phone', 'telephone', 'id_card_address',
        'district_id', 'email', 'identity_number', 'identity_type',
        'emergency_contact_name', 'emergency_contact_phone', 'notes',
        'is_active', 'unit_unlinked_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'unit_unlinked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public const UNIT_STATUS_WITH_UNIT = 'with_unit';

    public const UNIT_STATUS_NEVER_LINKED = 'never_linked';

    public const UNIT_STATUS_WITHOUT_UNIT = 'without_unit';

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }

    public function tenantUnits()
    {
        return $this->hasMany(Unit::class, 'tenant_resident_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function documents()
    {
        return $this->morphMany(ResidentDocument::class, 'documentable');
    }

    public function photos()
    {
        return $this->morphMany(ManagedFile::class, 'entity')->latest();
    }

    public function collectorAssignments()
    {
        return $this->hasMany(CollectorAssignment::class);
    }

    public function collectionLetters()
    {
        return $this->hasMany(CollectionLetter::class);
    }

    /**
     * Status hubungan penghuni-unit (with_unit / without_unit / never_linked), dipakai
     * frontend untuk badge "Tanpa Unit". Memakai units_count/tenant_units_count kalau
     * sudah di-load (lihat ResidentController::index) supaya daftar tidak N+1.
     */
    public function getUnitStatusAttribute(): string
    {
        $hasUnit = ($this->units_count ?? $this->units()->count()) > 0
            || ($this->tenant_units_count ?? $this->tenantUnits()->count()) > 0;

        if ($hasUnit) {
            return self::UNIT_STATUS_WITH_UNIT;
        }

        return $this->unit_unlinked_at ? self::UNIT_STATUS_WITHOUT_UNIT : self::UNIT_STATUS_NEVER_LINKED;
    }

    /**
     * Penghuni Tanpa Unit: sebelumnya sudah terhubung ke suatu unit, lalu hubungan itu
     * dibatalkan (unit dilepas/dihapus) dan tidak ada unit lain (sebagai pemilik maupun
     * penyewa). Penghuni yang belum pernah dihubungkan ke unit mana pun bukan kategori ini.
     */
    public function scopeUnitStatus(Builder $query, ?string $status): Builder
    {
        $hasUnit = fn (Builder $q) => $q->whereHas('units')->orWhereHas('tenantUnits');

        return $query->when($status, fn (Builder $q) => match ($status) {
            self::UNIT_STATUS_WITH_UNIT => $q->where($hasUnit),
            self::UNIT_STATUS_WITHOUT_UNIT => $q->whereNotNull('unit_unlinked_at')->whereNot($hasUnit),
            self::UNIT_STATUS_NEVER_LINKED => $q->whereNull('unit_unlinked_at')->whereNot($hasUnit),
            default => $q,
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
            $inner->where('id', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }));
    }

    public function scopeAddress(Builder $query, ?string $address): Builder
    {
        return $query->when($address, fn (Builder $q) => $q->where('id_card_address', 'like', "%{$address}%"));
    }

    public function scopeCluster(Builder $query, ?string $clusterId): Builder
    {
        return $query->when($clusterId, fn (Builder $q) => $q->whereHas('units', fn (Builder $inner) => $inner->where('cluster_id', $clusterId)));
    }

    public function scopeBlock(Builder $query, ?string $block): Builder
    {
        return $query->when($block, fn (Builder $q) => $q->whereHas('units', fn (Builder $inner) => $inner->where('block', 'like', "%{$block}%")));
    }
}
