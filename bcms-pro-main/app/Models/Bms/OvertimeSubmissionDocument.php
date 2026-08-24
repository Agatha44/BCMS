<?php

namespace App\Models\Bms;

use App\Models\AuthUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeSubmissionDocument extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_INACTIVE = 'inactive';

    public const TYPE_APPROVAL_MEMO = 'approval_memo';

    protected $connection = 'bcmis2';

    protected $table = 'overtime_submission_documents';

    protected $fillable = [
        'document_type',
        'document_name',
        'period_start',
        'period_end',
        'file_path',
        'file_name',
        'mime_type',
        'status',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'uploaded_by', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('document_type', $type);
    }

    /**
     * @param  Carbon|string  $date
     */
    public function scopeCoveringDate(Builder $query, $date): Builder
    {
        $dateString = $date instanceof Carbon ? $date->toDateString() : (string) $date;

        return $query
            ->whereDate('period_start', '<=', $dateString)
            ->whereDate('period_end', '>=', $dateString);
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->whereDate('period_end', '>=', now()->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->period_end !== null
            && $this->period_end->toDateString() < now()->toDateString();
    }
}
