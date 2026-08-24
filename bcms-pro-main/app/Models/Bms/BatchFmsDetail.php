<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AuthUser;

class BatchFmsDetail extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'batch_fms_details';

    protected $fillable = [
        'batch_number',
        'payment_request_id',
        'payment_reference',
        'fms_request_data',
        'fms_response_data',
        'fms_webhook_data',
        'fms_status',
        'fms_payment_id',
        'fms_status_message',
        'fms_error_message',
        'submitted_by',
        'submitted_at',
        'processed_at',
        'status_updated_at',
    ];

    protected $casts = [
        'fms_request_data' => 'array',
        'fms_response_data' => 'array',
        'fms_webhook_data' => 'array',
        'submitted_at' => 'datetime',
        'processed_at' => 'datetime',
        'status_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the batch this FMS detail belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimeBatch::class, 'batch_number', 'batch_number');
    }

    /**
     * Get the user who submitted the FMS payment
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'submitted_by', 'id');
    }

    /**
     * Scope to get only submitted FMS payments
     */
    public function scopeSubmitted($query)
    {
        return $query->where('fms_status', 'submitted');
    }

    /**
     * Scope to get only approved FMS payments
     */
    public function scopeApproved($query)
    {
        return $query->where('fms_status', 'approved');
    }

    /**
     * Scope to get only completed FMS payments
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('fms_status', ['completed', 'paid']);
    }

    /**
     * Scope to get only failed FMS payments
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('fms_status', ['rejected', 'failed', 'cancelled']);
    }

    /**
     * Scope to get FMS payments by payment request ID
     */
    public function scopeByPaymentRequestId($query, $paymentRequestId)
    {
        return $query->where('payment_request_id', $paymentRequestId);
    }
}

