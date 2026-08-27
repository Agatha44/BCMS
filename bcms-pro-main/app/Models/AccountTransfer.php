<?php

namespace App\Models;

use App\Constants\AccountTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountTransfer extends Model
{
    protected $table = 'account_transfer';

    protected $fillable = [
        'transfer_uuid',
        'client_reference',
        'from_account_id',
        'to_account_id',
        'amount',
        'status',
        'posted_at',
        'narration',
        'request_type',
        'action',
        'request_date',
        'approval_document_path',
        'approval_document_name',
        'approval_document_mime',
        'approved_by',
        'approved_at',
        'approval_comment',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'returned_by',
        'returned_at',
        'return_comment',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'posted_at' => 'datetime',
        'request_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'returned_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'created_by', 'id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'approved_by', 'id');
    }

    public function isPending(): bool
    {
        return $this->status === AccountTransferStatus::PENDING;
    }

    public function isReturned(): bool
    {
        return $this->status === AccountTransferStatus::RETURNED;
    }

    public function isPosted(): bool
    {
        return $this->status === AccountTransferStatus::POSTED;
    }

    public function hasApprovalDocument(): bool
    {
        return !empty($this->approval_document_path);
    }
}
