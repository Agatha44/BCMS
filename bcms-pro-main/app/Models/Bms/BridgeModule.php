<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BridgeModule extends Model
{
    protected $connection = 'bcmis2';
    protected $table = 'bridge_module';

    protected $fillable = [
        'icon',
        'title',
        'description',
        'module_id',
        'is_active',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'modified_by' => 'integer',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    // Disable automatic timestamps since the table uses created_at/modified_at
    public $timestamps = false;

    /**
     * Get the roles that have access to this module (many-to-many relationship)
     * One module can have one or more roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'bridge_module_role',
            'module_id',
            'role_id',
            'id',
            'id'
        )
        ->withPivot('is_active', 'created_by', 'modified_by', 'created_at', 'modified_at')
        ->withTimestamps();
    }

    /**
     * Get the active roles that have access to this module
     */
    public function activeRoles(): BelongsToMany
    {
        return $this->roles()->wherePivot('is_active', true);
    }

    /**
     * Get the bridge module role pivot records
     */
    public function moduleRoles(): HasMany
    {
        return $this->hasMany(BridgeModuleRole::class, 'module_id');
    }

    /**
     * Scope to get only active modules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }
}

