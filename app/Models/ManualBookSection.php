<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualBookSection extends Model
{
    protected $fillable = [
        'module', 'slug', 'title', 'summary', 'content', 'steps', 'tips', 'warnings',
        'faqs', 'roles', 'order', 'is_active', 'version', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'steps' => 'array',
        'tips' => 'array',
        'warnings' => 'array',
        'faqs' => 'array',
        'roles' => 'array',
        'is_active' => 'boolean',
    ];

    public function images()
    {
        return $this->morphMany(ManagedFile::class, 'entity')->latest();
    }

    public function reads()
    {
        return $this->hasMany(ManualBookRead::class);
    }

    /**
     * roles null/kosong berarti panduan ini terbuka untuk semua role yang login
     * (mis. pengenalan aplikasi). Kalau diisi, hanya role dalam daftar yang bisa lihat.
     */
    public function scopeVisibleToRoles(Builder $query, array $roleNames): Builder
    {
        return $query->where(function (Builder $q) use ($roleNames) {
            $q->whereNull('roles')->orWhere('roles', '[]');
            foreach ($roleNames as $role) {
                $q->orWhereJsonContains('roles', $role);
            }
        });
    }
}
