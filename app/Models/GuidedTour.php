<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GuidedTour extends Model
{
    protected $fillable = [
        'module', 'title', 'description', 'roles', 'is_active', 'auto_start',
        'order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'roles' => 'array',
        'is_active' => 'boolean',
        'auto_start' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(GuidedTourStep::class)->orderBy('order');
    }

    public function progress()
    {
        return $this->hasMany(UserTourProgress::class);
    }

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
