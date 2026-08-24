<?php

namespace App\Support\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared shift / date window helpers for collection reports.
 */
class ShiftWindow
{
    public const EVENING_SHIFT_ID = 3;

    public static function calendarDayBounds(string $date): array
    {
        return [$date . ' 00:00:00', $date . ' 23:59:59'];
    }

    public static function dateRangeBounds(string $fromDate, string $toDate): array
    {
        $start = $fromDate . ' 00:00:00';
        $end = Carbon::parse($toDate)->addDay()->format('Y-m-d') . ' 00:00:00';

        return [$start, $end];
    }

    public static function eveningBounds(string $shiftDate): array
    {
        $from = $shiftDate . ' 18:00:00';
        $to = Carbon::parse($shiftDate)->addDay()->format('Y-m-d') . ' 09:00:00';

        return [$from, $to];
    }

    /**
     * Bounds for toll_transaction queries on a given shift date.
     * Uses shift_start_time / shift_end_time from the shift table when available.
     */
    public static function shiftTransactionBounds(int $shiftId, string $shiftDate): array
    {
        $hasTimes = Schema::hasColumn('shift', 'shift_start_time')
            && Schema::hasColumn('shift', 'shift_end_time');

        if ($hasTimes) {
            $shift = DB::table('shift')
                ->select(['id', 'shift_start_time', 'shift_end_time'])
                ->where('id', $shiftId)
                ->first();

            $startTimeRaw = $shift?->shift_start_time ?? null;
            $endTimeRaw = $shift?->shift_end_time ?? null;

            if ($shift && $startTimeRaw && $endTimeRaw) {
                $startTime = self::normalizeTime($startTimeRaw);
                $endTime = self::normalizeTime($endTimeRaw);
                $from = $shiftDate . ' ' . $startTime;

                if (self::timeSpansMidnight($startTime, $endTime)) {
                    $to = Carbon::parse($shiftDate)->addDay()->format('Y-m-d') . ' ' . $endTime;
                } else {
                    $to = $shiftDate . ' ' . $endTime;
                }

                return [$from, $to];
            }
        }

        if ($shiftId === self::EVENING_SHIFT_ID) {
            return self::eveningBounds($shiftDate);
        }

        return self::calendarDayBounds($shiftDate);
    }

    private static function normalizeTime(mixed $time): string
    {
        if ($time instanceof \DateTimeInterface) {
            $value = $time->format('H:i:s');
        } else {
            $value = (string) $time;
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    private static function timeSpansMidnight(string $startTime, string $endTime): bool
    {
        $startParts = explode(':', $startTime);
        $endParts = explode(':', $endTime);
        $startValue = (int) ($startParts[0] ?? 0) * 100 + (int) ($startParts[1] ?? 0);
        $endValue = (int) ($endParts[0] ?? 0) * 100 + (int) ($endParts[1] ?? 0);

        return $startValue > $endValue;
    }
}
