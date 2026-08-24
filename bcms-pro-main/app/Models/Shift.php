<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $table = 'shift';
    
    protected $primaryKey = 'id';
    
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'name',
        'description',
        'shift_start_time',
        'shift_end_time',
    ];

    // Note: shift_start_time and shift_end_time are TIME columns
    // We'll handle them as strings in H:i:s format

    /**
     * Get shift start time based on shift ID from database
     * 
     * @param int $shiftId
     * @return string|null Time in H:i format
     */
    public static function getShiftStartTime($shiftId): ?string
    {
        $shift = self::find($shiftId);
        if (!$shift || !$shift->shift_start_time) {
            return null;
        }
        
        // TIME columns are returned as strings (H:i:s format)
        // Convert to H:i format
        $time = is_string($shift->shift_start_time) 
            ? $shift->shift_start_time 
            : $shift->shift_start_time->format('H:i:s');
        
        // Extract H:i from H:i:s
        return substr($time, 0, 5);
    }

    /**
     * Get shift end time based on shift ID from database
     * 
     * @param int $shiftId
     * @return string|null Time in H:i format
     */
    public static function getShiftEndTime($shiftId): ?string
    {
        $shift = self::find($shiftId);
        if (!$shift || !$shift->shift_end_time) {
            return null;
        }
        
        // TIME columns are returned as strings (H:i:s format)
        // Convert to H:i format
        $time = is_string($shift->shift_end_time) 
            ? $shift->shift_end_time 
            : $shift->shift_end_time->format('H:i:s');
        
        // Extract H:i from H:i:s
        return substr($time, 0, 5);
    }

    /**
     * Determine shift ID based on time using database shift times
     * 
     * @param \Carbon\Carbon $time
     * @return int|null Shift ID
     */
    public static function determineShiftFromTime($time): ?int
    {
        // Get all shifts from database
        $shifts = self::whereNotNull('shift_start_time')
            ->whereNotNull('shift_end_time')
            ->get();

        if ($shifts->isEmpty()) {
            // Fallback to hardcoded logic if database doesn't have shift times yet
            return self::determineShiftFromTimeFallback($time);
        }

        $timeHour = (int) $time->format('H');
        $timeMinute = (int) $time->format('i');
        $timeValue = $timeHour * 100 + $timeMinute;

        foreach ($shifts as $shift) {
            $startTime = $shift->shift_start_time;
            $endTime = $shift->shift_end_time;
            
            if (!$startTime || !$endTime) {
                continue;
            }

            // TIME columns are returned as strings (H:i:s format)
            $startTimeStr = is_string($startTime) ? $startTime : $startTime->format('H:i:s');
            $endTimeStr = is_string($endTime) ? $endTime : $endTime->format('H:i:s');

            // Extract hour and minute from H:i:s format
            $startParts = explode(':', $startTimeStr);
            $endParts = explode(':', $endTimeStr);
            
            $startHour = (int) ($startParts[0] ?? 0);
            $startMinute = (int) ($startParts[1] ?? 0);
            $startValue = $startHour * 100 + $startMinute;

            $endHour = (int) ($endParts[0] ?? 0);
            $endMinute = (int) ($endParts[1] ?? 0);
            $endValue = $endHour * 100 + $endMinute;

            // Check if shift spans midnight (e.g., 21:00 to 05:59)
            if ($startValue > $endValue) {
                // Shift spans midnight
                if ($timeValue >= $startValue || $timeValue <= $endValue) {
                    return $shift->id;
                }
            } else {
                // Normal shift (same day)
                if ($timeValue >= $startValue && $timeValue <= $endValue) {
                    return $shift->id;
                }
            }
        }

        return null;
    }

    /**
     * Check if a shift spans midnight (e.g., 21:00 to 05:59)
     * 
     * @param int $shiftId
     * @return bool
     */
    public static function shiftSpansMidnight($shiftId): bool
    {
        $shift = self::find($shiftId);
        if (!$shift || !$shift->shift_start_time || !$shift->shift_end_time) {
            return false;
        }

        $startTimeStr = is_string($shift->shift_start_time) 
            ? $shift->shift_start_time 
            : $shift->shift_start_time->format('H:i:s');
        $endTimeStr = is_string($shift->shift_end_time) 
            ? $shift->shift_end_time 
            : $shift->shift_end_time->format('H:i:s');

        $startParts = explode(':', $startTimeStr);
        $endParts = explode(':', $endTimeStr);
        
        $startValue = (int) ($startParts[0] ?? 0) * 100 + (int) ($startParts[1] ?? 0);
        $endValue = (int) ($endParts[0] ?? 0) * 100 + (int) ($endParts[1] ?? 0);

        // If start time > end time, shift spans midnight
        return $startValue > $endValue;
    }

    /**
     * Fallback method to determine shift using hardcoded logic
     * Used when database doesn't have shift times yet
     * 
     * @param \Carbon\Carbon $time
     * @return int|null Shift ID
     */
    private static function determineShiftFromTimeFallback($time): ?int
    {
        $hour = (int) $time->format('H');
        $minute = (int) $time->format('i');
        $timeValue = $hour * 100 + $minute;

        // Morning: 06:00 to 12:59
        if ($timeValue >= 600 && $timeValue <= 1259) {
            return 1;
        }
        
        // Afternoon: 13:00 to 20:59
        if ($timeValue >= 1300 && $timeValue <= 2059) {
            return 2;
        }
        
        // Evening: 21:00 to 05:59 (spans midnight)
        if ($timeValue >= 2100 || $timeValue <= 559) {
            return 3;
        }

        return null;
    }
}

