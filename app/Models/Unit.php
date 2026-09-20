<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    /**
     * property_type_id yang berupa lahan tanpa bangunan (Kavling), dipakai untuk
     * menentukan apakah unit kosong berlabel "Ready Stock" (Bangunan/Ruko) atau
     * "Tanah Kosong" (Kavling) - lihat getOccupancyStatusAttribute().
     */
    public const LAND_PROPERTY_TYPES = ['K', 'P'];

    public const OCCUPANCY_STATUS_READY_STOCK = 'ready_stock';

    public const OCCUPANCY_STATUS_TANAH_KOSONG = 'tanah_kosong';

    public const OCCUPANCY_STATUS_BOOKED = 'booked';

    public const OCCUPANCY_STATUS_OCCUPIED = 'occupied';

    public const OCCUPANCY_STATUS_LABELS = [
        self::OCCUPANCY_STATUS_READY_STOCK => 'Ready Stock',
        self::OCCUPANCY_STATUS_TANAH_KOSONG => 'Tanah Kosong',
        self::OCCUPANCY_STATUS_BOOKED => 'Booked',
        self::OCCUPANCY_STATUS_OCCUPIED => 'Occupied',
    ];

    /**
     * Nilai occupancy_id (lookup ke tabel occupancy_statuses, bukan accessor
     * occupancy_status di atas) yang dipasang otomatis begitu sebuah Unit berhasil
     * ditautkan ke seorang Resident/customer - lihat ResidentController::store() dan
     * UnitController::update().
     */
    public const OCCUPANCY_BOOKED_ID = '4';

    /** occupancy_id "Dihuni" - dipasang saat unit langsung aktif begitu penghuninya ditautkan. */
    public const OCCUPANCY_OCCUPIED_ID = '1';

    /**
     * Atribut unit yang aktif begitu penghuni ditautkan (tanpa proses serah terima terpisah):
     * status Aktif, occupancy Dihuni, dan tanggal aktif (dasar penagihan IPL) = hari ini
     * kecuali sudah tercatat sebelumnya.
     *
     * @return array{status_id: string, occupancy_id: string, handover_date: mixed}
     */
    public function activationAttributes(): array
    {
        return [
            'status_id' => 'AK',
            'occupancy_id' => self::OCCUPANCY_OCCUPIED_ID,
            'handover_date' => $this->handover_date ?? now()->toDateString(),
        ];
    }

    protected $fillable = [
        'id', 'va_number', 'resident_id', 'tenant_resident_id', 'billing_payer', 'cluster_id', 'block', 'lot_number', 'property_type_id',
        'building_area', 'land_area', 'handover_date', 'occupancy_id', 'status_id',
        'occupancy_role', 'tenancy_start_date', 'tenancy_end_date', 'latitude', 'longitude',
        'is_penalty_eligible', 'is_discount_eligible', 'discount_rule_id', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'building_area' => 'decimal:2',
        'land_area' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'handover_date' => 'date',
        'tenancy_start_date' => 'date',
        'tenancy_end_date' => 'date',
        'is_penalty_eligible' => 'boolean',
        'is_discount_eligible' => 'boolean',
        'balance' => 'decimal:2',
    ];

    protected $appends = ['deposit_balance', 'occupancy_status', 'occupancy_status_label'];

    public function resident()
    {
        return $this->belongsTo(Resident::class);
    }

    public function tenantResident()
    {
        return $this->belongsTo(Resident::class, 'tenant_resident_id');
    }

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }

    public function discountRule()
    {
        return $this->belongsTo(DiscountRule::class);
    }

    public function propertyType()
    {
        return $this->belongsTo(PropertyType::class);
    }

    public function occupancy()
    {
        return $this->belongsTo(OccupancyStatus::class, 'occupancy_id');
    }

    public function status()
    {
        return $this->belongsTo(ResidentStatus::class, 'status_id');
    }

    public function billings()
    {
        return $this->hasMany(Billing::class);
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function complaints()
    {
        return $this->hasMany(ResidentComplaint::class);
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function visits()
    {
        return $this->hasMany(CollectorVisit::class);
    }

    public function paymentPromises()
    {
        return $this->hasMany(PaymentPromise::class);
    }

    public function documents()
    {
        return $this->morphMany(ResidentDocument::class, 'documentable');
    }

    public function vehicles()
    {
        return $this->hasMany(UnitVehicle::class);
    }

    public function occupants()
    {
        return $this->hasMany(UnitOccupant::class);
    }

    public function collectorAssignments()
    {
        return $this->hasMany(CollectorAssignment::class);
    }

    public function collectionLetters()
    {
        return $this->hasMany(CollectionLetter::class);
    }

    public function deposits()
    {
        return $this->hasMany(UnitDeposit::class);
    }

    public function getDepositBalanceAttribute(): float
    {
        return (float) $this->balance;
    }

    /**
     * Apakah unit ini bertipe lahan (Kavling Developer/Penghuni) dan bukan bangunan/ruko.
     */
    public function isLandType(): bool
    {
        return in_array($this->property_type_id, self::LAND_PROPERTY_TYPES, true);
    }

    /**
     * Satu-satunya sumber kebenaran untuk "unit ini punya penghuni aktif atau tidak".
     * status_id = 'AK' adalah konvensi yang sudah dipakai di codebase (lihat
     * SupervisorTunggakanController) untuk menandai unit aktif/dihuni; resident()/
     * tenantResident() otomatis bernilai null kalau resident terkait sudah soft-deleted
     * atau memang belum pernah diisi, jadi penghuni lama/tidak aktif tidak akan
     * dihitung sebagai penghuni aktif (lihat dokumentasi status unit di TECHNICAL_SPEC).
     */
    public function hasActiveResident(): bool
    {
        if ($this->status_id !== 'AK') {
            return false;
        }

        return $this->resident !== null || $this->tenantResident !== null;
    }

    /**
     * Unit sudah ditautkan ke seorang penghuni/customer (occupancy_id = Booked, diset
     * otomatis saat penautan) tapi belum aktif/serah terima kunci. Penghuni yang
     * soft-deleted tidak dihitung, sama seperti hasActiveResident().
     */
    public function isBooked(): bool
    {
        return $this->occupancy_id === self::OCCUPANCY_BOOKED_ID
            && ($this->resident !== null || $this->tenantResident !== null);
    }

    /**
     * Status unit yang dihitung (bukan disimpan) dari tipe unit + ada/tidaknya penghuni
     * aktif, supaya tidak bisa terjadi data tidak konsisten seperti "status Ready Stock
     * tapi sebenarnya sudah ada penghuni". Unit yang sudah ditautkan ke penghuni tapi
     * belum aktif berstatus Booked, bukan lagi Ready Stock/Tanah Kosong.
     */
    public function getOccupancyStatusAttribute(): string
    {
        if ($this->hasActiveResident()) {
            return self::OCCUPANCY_STATUS_OCCUPIED;
        }

        if ($this->isBooked()) {
            return self::OCCUPANCY_STATUS_BOOKED;
        }

        return $this->isLandType() ? self::OCCUPANCY_STATUS_TANAH_KOSONG : self::OCCUPANCY_STATUS_READY_STOCK;
    }

    public function getOccupancyStatusLabelAttribute(): string
    {
        return self::OCCUPANCY_STATUS_LABELS[$this->occupancy_status];
    }

    /**
     * Filter berdasarkan status unit hasil hitungan (bukan kolom tersimpan), dipakai
     * oleh UnitController::index agar filter "Ready Stock/Tanah Kosong/Occupied" di
     * frontend konsisten dengan accessor occupancy_status di atas.
     */
    public function scopeOccupancyStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, function (Builder $q) use ($status) {
            $hasResident = fn (Builder $x) => $x->whereHas('resident')->orWhereHas('tenantResident');
            $isOccupied = fn (Builder $inner) => $inner->where('status_id', 'AK')->where($hasResident);
            $isBooked = fn (Builder $inner) => $inner->where('occupancy_id', self::OCCUPANCY_BOOKED_ID)->where($hasResident);

            return match ($status) {
                self::OCCUPANCY_STATUS_OCCUPIED => $q->where($isOccupied),
                self::OCCUPANCY_STATUS_BOOKED => $q->whereNot($isOccupied)->where($isBooked),
                self::OCCUPANCY_STATUS_READY_STOCK => $q
                    ->whereNotIn('property_type_id', self::LAND_PROPERTY_TYPES)
                    ->whereNot($isOccupied)
                    ->whereNot($isBooked),
                self::OCCUPANCY_STATUS_TANAH_KOSONG => $q
                    ->whereIn('property_type_id', self::LAND_PROPERTY_TYPES)
                    ->whereNot($isOccupied)
                    ->whereNot($isBooked),
                default => $q,
            };
        });
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
            $inner->where('id', 'like', "%{$search}%")
                ->orWhere('block', 'like', "%{$search}%")
                ->orWhere('lot_number', 'like', "%{$search}%")
                ->orWhere('va_number', 'like', "%{$search}%")
                ->orWhereHas('cluster', fn (Builder $c) => $c->where('name', 'like', "%{$search}%"))
                ->orWhereHas('resident', fn (Builder $c) => $c->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"));
        }));
    }
}
