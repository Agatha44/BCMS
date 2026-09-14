<?php

namespace App\Models\Bms;

use App\Models\AuthUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveApplication extends Model
{
    public const STATUS_APPLIED = 'Applied';
    public const STATUS_VERIFIED = 'Verified';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    protected $connection = 'bcmis2';

    protected $table = 'leave_applications';

    protected $fillable = [
        'applicant_id',
        'applicant_name',
        'pf_number',
        'leave_type',
        'start_date',
        'end_date',
        'days',
        'reason',
        'supportive_document_path',
        'supportive_document_name',
        'status',
        'verified_by',
        'verified_at',
        'verification_comment',
        'approved_by',
        'approved_at',
        'approval_comment',
        'rejected_by',
        'rejected_at',
        'rejected_at_stage',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'integer',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'applicant_id');
    }
}
