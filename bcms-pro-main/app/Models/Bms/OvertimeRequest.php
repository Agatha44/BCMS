<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\AuthUser;

class OvertimeRequest extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'overtime_requests';

    protected $primaryKey = 'id';

    protected $fillable = [
        'pf_number',
        'month',
        'total_overtime_hours',
        'total_days',
        'total_amount',
        'tax',
        'gross_pay',
        'gross_required',
        'salary',
        'half_salary',
        'net_pay',
        'overtime_daily_rate',
        'status',
        'workflow_status',
        'notes',
        'created_by',
        'updated_by',
        'validator_approved_by',
        'validator_approved_at',
        'validator_comment',
        'reviewer_approved_by',
        'reviewer_approved_at',
        'reviewer_comment',
        'batch_id',
        'external_status',
        'external_payment_request_id',
        'external_response',
        'external_status_updated_at',
    ];

    protected $casts = [
        'month' => 'date',
        'total_overtime_hours' => 'decimal:2',
        'total_days' => 'integer',
        'total_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'gross_required' => 'decimal:2',
        'salary' => 'decimal:2',
        'half_salary' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'overtime_daily_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'validator_approved_at' => 'datetime',
        'reviewer_approved_at' => 'datetime',
        'external_response' => 'array',
        'external_status_updated_at' => 'datetime',
    ];

    /**
     * Get the employee who submitted the request from bridge_employee table
     * Note: This is a manual relationship since pf_number references bridge_employee.pfno
     */
    public function getEmployeeAttribute()
    {
        if (!$this->pf_number) {
            return null;
        }
        
        return DB::connection('bcmis2')->table('bridge_employee')
            ->where('pfno', $this->pf_number)
            ->first();
    }

    /**
     * Get the user who created the request
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'created_by', 'id');
    }

    /**
     * Get the user who last updated the request
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'updated_by', 'id');
    }

    /**
     * Get the validator who approved the request (first level)
     */
    public function validatorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'validator_approved_by', 'id');
    }

    /**
     * Get the reviewer who approved the request (second level)
     */
    public function reviewerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'reviewer_approved_by', 'id');
    }

    /**
     * Get the batch this overtime request belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimeBatch::class, 'batch_id', 'id');
    }

    /**
     * Get the days for this overtime request
     */
    public function days(): HasMany
    {
        return $this->hasMany(OvertimeRequestDay::class, 'overtime_request_id', 'id');
    }

    /**
     * Get the history for this overtime request
     */
    public function history(): HasMany
    {
        return $this->hasMany(OvertimeRequestHistory::class, 'overtime_request_id', 'id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Scope to get only pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    /**
     * Scope to get requests pending validator approval (first level)
     */
    public function scopePendingValidator($query)
    {
        return $query->where('status', 'Pending');
    }

    /**
     * Scope to get requests pending reviewer approval (second level)
     */
    public function scopePendingReviewer($query)
    {
        return $query->where('status', 'Validator Approved');
    }

    /**
     * Scope to get requests approved by reviewer (ready for batch)
     */
    public function scopeReviewerApproved($query)
    {
        return $query->where('status', 'Reviewer Approved');
    }

    /**
     * Scope to get requests in batch
     */
    public function scopeInBatch($query)
    {
        return $query->where('status', 'In Batch')->whereNotNull('batch_id');
    }

    /**
     * Scope to get only rejected requests
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'Rejected');
    }

    /**
     * Scope to get requests returned to applicant for correction
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'Returned');
    }

    /**
     * Scope to filter by employee PF number
     */
    public function scopeForEmployee($query, $pfNumber)
    {
        return $query->where('pf_number', $pfNumber);
    }

    /**
     * Scope to filter by month
     */
    public function scopeForMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Get month display attribute (e.g., "January 2024")
     */
    public function getMonthDisplayAttribute(): string
    {
        return $this->month ? $this->month->format('F Y') : '';
    }
}

