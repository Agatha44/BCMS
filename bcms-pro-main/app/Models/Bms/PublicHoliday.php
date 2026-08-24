<?php

namespace App\Models\Bms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublicHoliday extends Model
{
    use HasFactory;

    protected $connection = 'bcmis2';
    protected $table = 'public_holidays';
    protected $primaryKey = 'id';
    public $incrementing = true;
    const UPDATED_AT = null;

    protected $fillable = [
        'holiday_name',
        'holiday_date',
        'holiday_type',
        'is_active',
        'description',
        'created_by',
        'modified_by',
        'modified_at',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_active' => 'boolean',
        'modified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Check if a date is a public holiday
     */
    public static function isHoliday(string $date): bool
    {
        return self::where('holiday_date', $date)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get holiday by date
     */
    public static function getHolidayByDate(string $date): ?self
    {
        return self::where('holiday_date', $date)
            ->where('is_active', true)
            ->first();
    }
}
