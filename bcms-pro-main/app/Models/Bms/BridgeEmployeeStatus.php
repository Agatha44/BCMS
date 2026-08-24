<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BridgeEmployeeStatus extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'bridge_employee_status';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'national_id',
        'employee_status',
        'reason_id',
        'cby',
        'cdate',
        'description',
        'proposed_changes',
        'start_month',
        'start_year',
        'es_id',
        'statusdate',
        'verified_by',
        'verify_date',
        'approved_by',
        'approve_date',
        'verified',
        'approved',
        'previous_employee_status',
    ];

    protected $casts = [
        'cdate' => 'datetime',
        'statusdate' => 'datetime',
        'verify_date' => 'datetime',
        'approve_date' => 'datetime',
        'verified' => 'boolean',
        'approved' => 'boolean',
        'reason_id' => 'integer',
        'es_id' => 'integer',
        'start_month' => 'integer',
        'start_year' => 'integer',
        'proposed_changes' => 'array',
    ];

    /**
     * Get the employee that owns this status
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(BridgeEmployee::class, 'national_id', 'national_id');
    }

    /**
     * Scope to get pending approvals
     */
    public function scopePendingApproval($query)
    {
        return $query->where('approved', false)
            ->whereIn('employee_status', [
                \App\Constants\EmployeeStatus::PENDING_APPROVAL,
                \App\Constants\EmployeeStatus::PENDING_CREATION,
                \App\Constants\EmployeeStatus::PENDING_TERMINATION,
                \App\Constants\EmployeeStatus::PENDING_DELETION,
                \App\Constants\EmployeeStatus::PENDING_UPDATE,
            ]);
    }

    /**
     * Scope to get approved statuses
     */
    public function scopeApproved($query)
    {
        return $query->where('approved', true);
    }

    /**
     * Scope to get by national ID
     */
    public function scopeByNationalId($query, $nationalId)
    {
        return $query->where('national_id', $nationalId);
    }

    /**
     * Scope to get latest status for an employee
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('statusdate', 'desc')
            ->orderBy('id', 'desc');
    }
}

