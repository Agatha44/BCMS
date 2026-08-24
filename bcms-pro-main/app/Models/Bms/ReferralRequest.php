<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralRequest extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'referral_request';

    protected $fillable = [
        'module_code',
        'action_type',
        'national_id',
        'reference_id',
        'status',
        'initiated_by',
        'approved_by',
        'remarks',
        'actioned_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    // Disable updated_at timestamp since the table doesn't have this column
    const UPDATED_AT = null;

    // Status constants
    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';

    // Action type constants
    const ACTION_CREATE = 'CREATE';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';
    const ACTION_TERMINATE = 'TERMINATE';

    // Module code constants
    const MODULE_EMPLOYMENT_MANAGEMENT = 'EMPLOYMENT_MANAGEMENT';

    /**
     * Get the user who initiated the request
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AuthUser::class, 'initiated_by', 'id');
    }

    /**
     * Get the user who approved/rejected the request
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AuthUser::class, 'approved_by', 'id');
    }

    /**
     * Scope to get pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter by module
     */
    public function scopeByModule($query, $moduleCode)
    {
        return $query->where('module_code', $moduleCode);
    }

    /**
     * Scope to filter by action type
     */
    public function scopeByActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to filter by national ID
     */
    public function scopeByNationalId($query, $nationalId)
    {
        return $query->where('national_id', $nationalId);
    }

    /**
     * Check if request is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if request is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

