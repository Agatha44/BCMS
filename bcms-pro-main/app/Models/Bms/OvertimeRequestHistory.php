<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\AuthUser;

class OvertimeRequestHistory extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'overtime_request_history';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'overtime_request_id',
        'action',
        'status',
        'workflow_status',
        'performed_by',
        'performed_by_role',
        'comment',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the overtime request that owns this history entry
     */
    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id', 'id');
    }

    /**
     * Get the user who performed the action
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(AuthUser::class, 'performed_by', 'id');
    }

    /**
     * Get timestamp attribute alias
     */
    public function getTimestampAttribute()
    {
        return $this->created_at;
    }
}

