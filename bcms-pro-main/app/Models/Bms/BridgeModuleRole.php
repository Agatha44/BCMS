<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeModuleRole extends Model
{
    protected $connection = 'bcmis2';
    protected $table = 'bridge_module_role';

    protected $fillable = [
        'module_id',
        'role_id',
        'is_active',
        'created_by',
        'modified_by',
    ];

    protected $casts = [
        'module_id' => 'integer',
        'role_id' => 'integer',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'modified_by' => 'integer',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
    ];

    /**
     * Get the module that owns this assignment
     * One module can have one or more roles through this bridge table
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(BridgeModule::class, 'module_id');
    }

    /**
     * Get the role that owns this assignment
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Scope to get only active assignments
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

