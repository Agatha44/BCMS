<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AuthUser;

class OvertimeBatch extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'overtime_batches';

    protected $fillable = [
        'batch_number',
        'batch_name',
        'status',
        'total_amount',
        'total_requests',
        'external_batch_id',
        'external_payment_request_id',
        'external_response',
        'erms_status',
        'payment_reference',
        'payment_status',
        'payment_reference_response',
        'notes',
        'created_by',
        'updated_by',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'total_requests' => 'integer',
        'external_response' => 'array',
        'erms_status' => 'integer',
        'payment_status' => 'string',
        'payment_reference_response' => 'array',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created the batch
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'created_by', 'id');
    }

    /**
     * Get the user who last updated the batch
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'updated_by', 'id');
    }

    /**
     * Get all overtime requests in this batch
     */
    public function overtimeRequests(): HasMany
    {
        return $this->hasMany(OvertimeRequest::class, 'batch_id', 'id');
    }

    /**
     * Scope to get only draft batches
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get only submitted batches
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope to get only completed batches
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Generate unique batch number
     */
    public static function generateBatchNumber(): string
    {
        $prefix = 'OTB';
        $date = now()->format('Ymd');
        $lastBatch = self::where('batch_number', 'like', "{$prefix}-{$date}-%")
            ->orderBy('batch_number', 'desc')
            ->first();

        if ($lastBatch) {
            $lastNumber = (int) substr($lastBatch->batch_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$prefix}-{$date}-{$newNumber}";
    }
}

