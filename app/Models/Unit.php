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
    ];

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
