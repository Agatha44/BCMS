<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequestDay extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';

    protected $table = 'overtime_request_days';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'overtime_request_id',
        'day_date',
        'overtime_hours',
        'daily_rate',
        'amount',
        'overtime_type',
        'is_holiday',
        'special_task_id',
        'overtime_reason',
    ];

    protected $casts = [
        'day_date' => 'date',
        'overtime_hours' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'is_holiday' => 'boolean',
        'special_task_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the overtime request that owns this day
     */
    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class, 'overtime_request_id', 'id');
    }

    /**
     * Get the special task if this is a special task overtime
     */
    public function specialTask(): BelongsTo
    {
        return $this->belongsTo(SpecialTask::class, 'special_task_id', 'id');
    }
}

