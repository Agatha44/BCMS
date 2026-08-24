<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpecialTask extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';
    protected $table = 'special_tasks';
    protected $primaryKey = 'id';
    public $incrementing = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'task_name',
        'description',
        'pf_number',
        'start_date',
        'end_date',
        'overtime_rule',
        'custom_overtime_threshold',
        'is_pre_approved',
        'status',
        'notes',
        'created_by',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_pre_approved' => 'boolean',
        'custom_overtime_threshold' => 'decimal:2',
        'modified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Get active special task for employee on a specific date
     */
    public static function getTaskForDate(string $pfNumber, string $date): ?self
    {
        return self::where('pf_number', $pfNumber)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Check if employee has active special task on date
     */
    public static function hasActiveTask(string $pfNumber, string $date): bool
    {
        return self::getTaskForDate($pfNumber, $date) !== null;
    }
}
