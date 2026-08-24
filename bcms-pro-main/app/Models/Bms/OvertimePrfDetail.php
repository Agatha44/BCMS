<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AuthUser;

class OvertimePrfDetail extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'overtime_prf_details';

    protected $fillable = [
        'batch_number',
        'payment_reference',
        'amount',
        'prf_request_data',
        'prf_response_data',
        'prf_url',
        'status',
        'error_message',
        'submitted_by',
        'submitted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'prf_request_data' => 'array',
        'prf_response_data' => 'array',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the batch this PRF detail belongs to
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimeBatch::class, 'batch_number', 'batch_number');
    }

    /**
     * Get the user who submitted the PRF
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'submitted_by', 'id');
    }

    /**
     * Scope to get only submitted PRFs
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope to get only failed PRFs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get only PRFs that were created successfully
     */
    public function scopePrfCreated($query)
    {
        return $query->where('status', 'prf_created');
    }

    /**
     * Get all votes for this PRF detail
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PrfVote::class, 'prf_detail_id', 'id');
    }
}

