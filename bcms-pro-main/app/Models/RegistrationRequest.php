<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_type',
        'plate_number',
        'body_type_id',
        'body_type_name',
        'owner_name',
        'owner_phone',
        'owner_email',
        'nida_number',
        'exemption_reason',
        'update_details',
        'registration_card_path',
        'status',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'comments'
    ];

    protected $casts = [
        'update_details' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the body type that owns the registration request.
     */
    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class, 'body_type_id');
    }

    /**
     * Get the user who submitted the request.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who reviewed the request.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope a query to filter by request type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('request_type', $type);
    }

    /**
     * Get the status text attribute.
     */
    public function getStatusTextAttribute(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Get the request type text attribute.
     */
    public function getRequestTypeTextAttribute(): string
    {
        return ucfirst($this->request_type);
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the request can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        return $this->isPending();
    }

    /**
     * Get the formatted submitted date.
     */
    public function getFormattedSubmittedAtAttribute(): string
    {
        return $this->submitted_at->format('M d, Y H:i');
    }

    /**
     * Get the formatted reviewed date.
     */
    public function getFormattedReviewedAtAttribute(): string
    {
        return $this->reviewed_at ? $this->reviewed_at->format('M d, Y H:i') : 'N/A';
    }
}
