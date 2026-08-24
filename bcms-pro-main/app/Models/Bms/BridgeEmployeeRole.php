<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeEmployeeRole extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_employee_role';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'national_id',
        'role_id',
        'from_date',
        'to_date',
        'is_active',
        'created_by',
        'created_at',
        'modified_by',
        'modified_at',
        'description',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'modified_at' => 'datetime',
        'revoked_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
        'role_id' => 'integer',
    ];

    /**
     * Get the employee that owns this role assignment
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(BridgeEmployee::class, 'national_id', 'national_id');
    }

    /**
     * Get the BMS role assigned
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Scope to get only active role assignments
     */
    public function scopeActive($query)
    {
        $today = now()->toDateString();

        return $query->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('from_date')->orWhere('from_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('to_date')->orWhere('to_date', '>=', $today);
            });
    }

    /**
     * Scope to get revoked role assignments
     */
    public function scopeRevoked($query)
    {
        return $query->where('is_active', false)
            ->whereNotNull('revoked_at');
    }

    /**
     * Scope to get by national ID
     */
    public function scopeByNationalId($query, $nationalId)
    {
        return $query->where('national_id', $nationalId);
    }

    /**
     * Scope to get by role ID
     */
    public function scopeByRoleId($query, $roleId)
    {
        return $query->where('role_id', $roleId);
    }
}

