<?php

namespace App\Models;

use App\Models\AuthRoleModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuthRole extends Model
{
    protected $table = 'auth_role';

    protected $fillable = ['name', 'description', 'is_active', 'created_by', 'updated_by'];

    protected $casts = [
        'is_active' => 'integer',
    ];

    public function userRoles(): HasMany
    {
        return $this->hasMany(AuthUserRole::class, 'role_id');
    }

    public function roleModules(): HasMany
    {
        return $this->hasMany(AuthRoleModule::class, 'auth_role_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(AuthUser::class, 'auth_user_role', 'role_id', 'user_id')
            ->withPivot('is_active')
            ->wherePivot('is_active', 1)
            ->withTimestamps();
    }

    /**
     * Get the actions that this role has access to
     */
    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(AuthAction::class, 'auth_role_action', 'role_id', 'action_id', 'id', 'id')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Get the role actions pivot table
     */
    public function roleActions(): HasMany
    {
        return $this->hasMany(AuthRoleAction::class, 'role_id');
    }

    /**
     * Scope to get only active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}
