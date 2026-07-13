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
        'created_by', 'updated_by',
    ];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
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

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $q) => $q->where(function (Builder $inner) use ($search) {
            $inner->where('id', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        }));
    }
}
