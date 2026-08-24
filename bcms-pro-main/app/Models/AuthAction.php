<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuthAction extends Model
{
    protected $table = 'auth_action';
    
    protected $fillable = [
        'parent_id',
        'title',
        'controller_id',
        'action_id',
        'route',
        'menu_icon',
        'on_menu',
        'order_no',
        'is_active',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'on_menu' => 'integer',
        'order_no' => 'integer',
        'is_active' => 'integer',
    ];

    /**
     * Get the parent action
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AuthAction::class, 'parent_id');
    }

    /**
     * Get the child actions
     */
    public function children(): HasMany
    {
        return $this->hasMany(AuthAction::class, 'parent_id');
    }

    /**
     * Get the roles that have access to this action
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(AuthRole::class, 'auth_role_action', 'action_id', 'role_id', 'id', 'id')
            ->withPivot('is_active')
            ->withTimestamps();
    }

    /**
     * Scope to get only active actions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Scope to get only menu items
     */
    public function scopeOnMenu($query)
    {
        return $query->where('on_menu', 1);
    }

    /**
     * Get status text attribute
     */
    public function getStatusTextAttribute(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    /**
     * Get full route attribute
     */
    public function getFullRouteAttribute(): string
    {
        if ($this->route) {
            return $this->route;
        }
        
        if ($this->action_id) {
            return $this->controller_id . '/' . $this->action_id;
        }
        
        return $this->controller_id;
    }
}
