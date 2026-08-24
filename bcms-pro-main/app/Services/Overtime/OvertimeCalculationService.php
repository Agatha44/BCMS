<?php

namespace App\Services\Overtime;

use App\Models\Bms\AttendanceLog;
use App\Models\Bms\PublicHoliday;
use App\Models\Bms\SpecialTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class OvertimeCalculationService
{
    const STANDARD_WORK_HOURS = 9;

    public function getOvertimeInfo(string $pfNumber, string $date): ?array
    {
        try {
            $attendance = AttendanceLog::where('pf_number', $pfNumber)
                ->whereDate('time_in', $date)
                ->first();

            if (!$attendance || !$attendance->time_in) {
                return null;
            }

            $timeIn = Carbon::parse($attendance->time_in);
            $timeOut = $attendance->time_out ? Carbon::parse($attendance->time_out) : $timeIn->copy()->addHours(8);
            $totalWorkedMinutes = $timeIn->diffInMinutes($timeOut);
            $totalWorkedHours = $totalWorkedMinutes / 60;

            $isHoliday = PublicHoliday::isHoliday($date);
            $holiday = $isHoliday ? PublicHoliday::getHolidayByDate($date) : null;
            $specialTask = SpecialTask::getTaskForDate($pfNumber, $date);

            $overtimeInfo = $this->calculateOvertimeHours(
                $timeIn,
                $timeOut,
                $totalWorkedHours,
                $isHoliday,
                $holiday,
                $specialTask
            );

            return [
                'timeIn' => $timeIn->format('H:i:s'),
                'timeOut' => $attendance->time_out ? $timeOut->format('H:i:s') : null,
                'date' => $date,
                'totalWorkedHours' => round($totalWorkedHours, 2),
                'overtimeHours' => $overtimeInfo['overtimeHours'],
                'overtimeType' => $overtimeInfo['overtimeType'],
                'isHoliday' => $isHoliday,
                'holidayName' => $holiday ? $holiday->holiday_name : null,
                'hasSpecialTask' => $specialTask !== null,
                'specialTask' => $specialTask ? [
                    'id' => $specialTask->id,
                    'name' => $specialTask->task_name,
                    'isPreApproved' => $specialTask->is_pre_approved,
                    'overtimeRule' => $specialTask->overtime_rule,
                ] : null,
                'reason' => $overtimeInfo['reason'],
                'canApply' => $overtimeInfo['overtimeHours'] > 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error getting overtime info', [
                'pf_number' => $pfNumber,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function calculateOvertimeHours(
        Carbon $timeIn,
        Carbon $timeOut,
        float $totalWorkedHours,
        bool $isHoliday,
        ?PublicHoliday $holiday,
        ?SpecialTask $specialTask
    ): array {
        $overtimeHours = 0;
        $overtimeType = 'regular';
        $reason = 'Standard overtime calculation (hours worked - 9 hours)';

        if ($specialTask) {
            $overtimeType = 'special_task';
            $reason = "Special Task: {$specialTask->task_name}";

            switch ($specialTask->overtime_rule) {
                case 'all_hours':
                    $overtimeHours = round($totalWorkedHours, 2);
                    $reason .= ' - All hours count as overtime';
                    break;

                case 'custom':
                    $threshold = $specialTask->custom_overtime_threshold;
                    $overtimeHours = $totalWorkedHours >= $threshold ? round($totalWorkedHours, 2) : 0;
                    $reason .= " - Custom threshold: {$threshold} hours";
                    break;

                case 'standard':
                default:
                    $overtimeHours = $totalWorkedHours >= self::STANDARD_WORK_HOURS ? round($totalWorkedHours, 2) : 0;
                    $reason .= ' - Standard 9-hour rule';
                    break;
            }
        } elseif ($isHoliday) {
            $overtimeType = 'holiday';
            $overtimeHours = round($totalWorkedHours, 2);
            $reason = "Public Holiday: {$holiday->holiday_name} - All hours count as overtime";
        } else {
            $overtimeType = 'regular';
            $overtimeHours = max(0, round($totalWorkedHours - self::STANDARD_WORK_HOURS, 2));
            $reason = 'Standard overtime calculation (hours worked - 9 hours)';
        }

        return [
            'overtimeHours' => $overtimeHours,
            'overtimeType' => $overtimeType,
            'reason' => $reason,
        ];
    }
}
