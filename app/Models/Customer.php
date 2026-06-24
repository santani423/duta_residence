<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasStringPrimaryKey, SoftDeletes;

    protected $fillable = [
        'id', 'name', 'cluster_id', 'block', 'lot_number', 'property_type_id',
        'phone', 'telephone', 'id_card_address', 'district_id', 'building_area',
        'land_area', 'email', 'handover_date', 'occupancy_id', 'status_id',
        'is_penalty_eligible', 'is_discount_eligible', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'building_area' => 'decimal:2',
        'land_area' => 'decimal:2',
        'handover_date' => 'date',
        'is_penalty_eligible' => 'boolean',
        'is_discount_eligible' => 'boolean',
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
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
        return $this->belongsTo(CustomerStatus::class, 'status_id');
    }

    public function district()
    {
        return $this->belongsTo(District::class);
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

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
            $inner->where('id', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('block', 'like', "%{$search}%")
                ->orWhere('lot_number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%");
        }));
    }
}
