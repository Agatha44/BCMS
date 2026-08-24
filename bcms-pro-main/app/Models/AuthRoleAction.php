<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthRoleAction extends Model
{
    protected $table = 'auth_role_action';
    
    protected $fillable = [
        'role_id',
        'action_id',
        'is_active',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'is_active' => 'integer',
    ];

    /**
     * Get the role that owns this role action
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AuthRole::class, 'role_id');
    }

    /**
     * Get the action that owns this role action
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(AuthAction::class, 'action_id', 'id');
    }

    /**
     * Scope to get only active role actions
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
