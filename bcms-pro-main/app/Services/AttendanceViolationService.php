<?php

namespace App\Services;

use App\Models\Bms\AttendanceLog;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\BridgeShift;
use App\Models\Bms\BridgeShiftDepartment;
use App\Models\Shift;
use App\Models\AuthUser;
use App\Models\Counter;
use App\Models\Notifications\Notifications;
use App\Services\Notifications\BmsEmailTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceViolationService
{
    const GRACE_PERIOD_MINUTES = 10;

    /**
     * Check attendance violations for a specific date
     */
    public function checkViolations(?string $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        $results = [
            'date' => $date->format('Y-m-d'),
            'late_arrivals' => [],
            'early_departures' => [],
            'errors' => [],
        ];

        try {
            $attendanceLogs = AttendanceLog::whereDate('time_in', $date->format('Y-m-d'))
                ->whereNotNull('time_in')
                ->whereNotNull('pf_number')
                ->orderBy('pf_number')
                ->orderBy('time_in')
                ->get()
                ->groupBy('pf_number');

            foreach ($attendanceLogs as $pfNumber => $logs) {
                $firstLog = $logs->sortBy('time_in')->first();
                $lastLog = $logs->filter(fn($log) => $log->time_out !== null)
                    ->sortByDesc('time_out')->first();

                if (!$firstLog) {
                    continue;
                }

                try {
                    $logDate = Carbon::parse($firstLog->time_in)->format('Y-m-d');
                    if ($logDate !== $date->format('Y-m-d')) {
                        continue;
                    }

                    $firstLog = AttendanceLog::find($firstLog->id);
                    if (!$firstLog) {
                        continue;
                    }

                    $timeIn = Carbon::parse($firstLog->time_in);
                    $employee = $this->getEmployeeByPfNumber($pfNumber);
                    
                    if (!$employee) {
                        Log::warning('Employee not found for violation check', ['pf_number' => $pfNumber]);
                        continue;
                    }
                    
                    $shiftId = $this->getShiftIdFromDepartment($employee->department_id ?? null, $timeIn);

                    if ($shiftId) {
                        $lateArrival = $this->checkLateArrival($firstLog, $shiftId, $timeIn);
                        if ($lateArrival) {
                            $results['late_arrivals'][] = $lateArrival;
                            $this->sendNotification($lateArrival, 'late_arrival');
                            $firstLog = AttendanceLog::find($firstLog->id);
                        }

                        $timeOutToCheck = $this->getTimeOutToCheck($firstLog, $lastLog, $pfNumber, $timeIn, $date);
                        if ($timeOutToCheck) {
                            $earlyDeparture = $this->checkEarlyDeparture($firstLog, $shiftId, $timeOutToCheck);
                            if ($earlyDeparture) {
                                $results['early_departures'][] = $earlyDeparture;
                                $this->sendNotification($earlyDeparture, 'early_departure');
                            }
                        }
                    } else {
                        Log::warning('Could not determine shift for attendance log', [
                            'log_id' => $firstLog->id,
                            'pf_number' => $pfNumber,
                            'time_in' => $timeIn->format('Y-m-d H:i:s'),
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'pf_number' => $pfNumber ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Error checking violations for attendance log', [
                        'log_id' => $firstLog->id ?? null,
                        'pf_number' => $pfNumber,
                        'date' => $date->format('Y-m-d'),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Error in checkViolations', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            $results['errors'][] = ['error' => $e->getMessage()];
            return $results;
        }
    }

    /**
     * Check violations for a specific log record (called incrementally during sync)
     */
    public function checkViolationsForLog(AttendanceLog $log): void
    {
        if (!$log || !$log->id || !$log->time_in) {
            return;
        }

        try {
            $log = AttendanceLog::find($log->id);
            if (!$log || !$log->time_in) {
                return;
            }

            $timeIn = Carbon::parse($log->time_in);
            $employee = $this->getEmployeeByPfNumber($log->pf_number);
            
            if (!$employee) {
                Log::warning('Employee not found for violation check', [
                    'log_id' => $log->id,
                    'pf_number' => $log->pf_number,
                ]);
                return;
            }
            
            $shiftId = $this->getShiftIdFromDepartment($employee->department_id ?? null, $timeIn);

            if (!$shiftId) {
                Log::warning('Could not determine shift for attendance log', [
                    'log_id' => $log->id,
                    'pf_number' => $log->pf_number,
                    'time_in' => $timeIn->format('Y-m-d H:i:s'),
                ]);
                return;
            }

            $lateArrival = $this->checkLateArrival($log, $shiftId, $timeIn);
            if ($lateArrival) {
                $this->sendNotification($lateArrival, 'late_arrival');
            }

            if ($log->time_out) {
                $timeOut = Carbon::parse($log->time_out);
                
                if ($this->employeeIsTollCollector($log->pf_number)) {
                    $counterTimeOut = $this->getTollCollectorTimeoutFromCounter($log->pf_number, $timeIn);
                    if ($counterTimeOut) {
                        $timeOut = $counterTimeOut;
                    }
                }
                
                $earlyDeparture = $this->checkEarlyDeparture($log, $shiftId, $timeOut);
                if ($earlyDeparture) {
                    $this->sendNotification($earlyDeparture, 'early_departure');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error checking violations for log', [
                'log_id' => $log->id ?? null,
                'pf_number' => $log->pf_number ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if an employee has a late arrival
     */
    private function checkLateArrival(AttendanceLog $log, int $shiftId, Carbon $timeIn): ?array
    {
        $shift = BridgeShift::find($shiftId);
        if (!$shift) {
            return null;
        }

        $shiftStart = $this->calculateShiftTime($shiftId, $timeIn, 'start');
        if (!$shiftStart) {
            return null;
        }

        $minutesLate = $shiftStart->diffInMinutes($timeIn, false);

        if ($minutesLate < -self::GRACE_PERIOD_MINUTES) {
            Log::info('Early arrival detected (not flagged as violation)', [
                'log_id' => $log->id,
                'pf_number' => $log->pf_number,
                'time_in' => $timeIn->format('Y-m-d H:i:s'),
                'shift_start' => $shiftStart->format('Y-m-d H:i:s'),
                'minutes_early' => abs($minutesLate),
            ]);
            return null;
        }

        if ($minutesLate > self::GRACE_PERIOD_MINUTES) {
            $employee = $this->getEmployeeByPfNumber($log->pf_number);
            $log = AttendanceLog::find($log->id);
            
            if (!$log) {
                return null;
            }

            if (!$log->is_late_arrival) {
                AttendanceLog::where('id', $log->id)->update(['is_late_arrival' => true]);
                Log::info('Late arrival violation status updated', [
                    'log_id' => $log->id,
                    'pf_number' => $log->pf_number,
                    'minutes_late' => $minutesLate,
                ]);
            }

            return [
                'log_id' => $log->id,
                'pf_number' => $log->pf_number,
                'employee_name' => $employee ? $this->formatEmployeeName($employee) : 'Unknown',
                'shift_id' => $shiftId,
                'shift_name' => $shift->shift_name ?? 'Unknown',
                'expected_time' => $shiftStart->format('Y-m-d H:i:s'),
                'actual_time' => $timeIn->format('Y-m-d H:i:s'),
                'minutes_late' => $minutesLate,
                'date' => $timeIn->format('Y-m-d'),
            ];
        }

        return null;
    }

    /**
     * Check if employee has early departure
     */
    private function checkEarlyDeparture(AttendanceLog $log, int $shiftId, Carbon $timeOut): ?array
    {
        $shift = BridgeShift::find($shiftId);
        if (!$shift) {
            return null;
        }

        $shiftEnd = $this->calculateShiftTime($shiftId, $timeOut, 'end');
        if (!$shiftEnd) {
            return null;
        }

        if ($timeOut->lt($shiftEnd)) {
            $employee = $this->getEmployeeByPfNumber($log->pf_number);
            $minutesEarly = $timeOut->diffInMinutes($shiftEnd, false);
            $log = AttendanceLog::find($log->id);
            
            if (!$log) {
                return null;
            }

            if (!$log->is_early_departure) {
                AttendanceLog::where('id', $log->id)->update(['is_early_departure' => true]);
                Log::info('Early departure violation status updated', [
                    'log_id' => $log->id,
                    'pf_number' => $log->pf_number,
                    'minutes_early' => abs($minutesEarly),
                ]);
            }

            return [
                'log_id' => $log->id,
                'pf_number' => $log->pf_number,
                'employee_name' => $employee ? $this->formatEmployeeName($employee) : 'Unknown',
                'shift_id' => $shiftId,
                'shift_name' => $shift->shift_name ?? 'Unknown',
                'expected_time' => $shiftEnd->format('Y-m-d H:i:s'),
                'actual_time' => $timeOut->format('Y-m-d H:i:s'),
                'minutes_early' => abs($minutesEarly),
                'date' => $timeOut->format('Y-m-d'),
            ];
        }

        return null;
    }

    /**
     * Calculate shift start or end time based on shift and reference time
     */
    private function calculateShiftTime(int $shiftId, Carbon $referenceTime, string $type): ?Carbon
    {
        $shift = BridgeShift::find($shiftId);
        if (!$shift) {
            return null;
        }

        $timeField = $type === 'start' ? 'start_time' : 'end_time';
        $timeStr = $this->getShiftTimeString($shift->$timeField);
        
        if (!$timeStr) {
            return null;
        }

        $spansMidnight = $this->bridgeShiftSpansMidnight($shiftId);
        $referenceValue = (int) $referenceTime->format('Hi');
        $timeValue = (int) str_replace(':', '', $timeStr);

        if ($spansMidnight) {
            $startTimeValue = (int) str_replace(':', '', $this->getShiftTimeString($shift->start_time));
            
            if ($type === 'start') {
                return $referenceValue < $startTimeValue
                    ? Carbon::parse($referenceTime->copy()->subDay()->format('Y-m-d') . ' ' . $timeStr)
                    : Carbon::parse($referenceTime->format('Y-m-d') . ' ' . $timeStr);
            } else {
                return $referenceValue < $startTimeValue
                    ? Carbon::parse($referenceTime->format('Y-m-d') . ' ' . $timeStr)
                    : Carbon::parse($referenceTime->copy()->addDay()->format('Y-m-d') . ' ' . $timeStr);
            }
        }

        return Carbon::parse($referenceTime->format('Y-m-d') . ' ' . $timeStr);
    }

    /**
     * Get time string from shift time field
     */
    private function getShiftTimeString($time): ?string
    {
        if (!$time) {
            return null;
        }

        $timeStr = is_string($time) ? $time : $time->format('H:i:s');
        return substr($timeStr, 0, 5);
    }

    /**
     * Get time out to check for early departure
     */
    private function getTimeOutToCheck($firstLog, $lastLog, string $pfNumber, Carbon $timeIn, Carbon $date): ?Carbon
    {
        if ($firstLog->time_out) {
            $timeOut = Carbon::parse($firstLog->time_out);
        } elseif ($lastLog && $lastLog->id !== $firstLog->id && $lastLog->time_out) {
            $lastLogDate = Carbon::parse($lastLog->time_in)->format('Y-m-d');
            if ($lastLogDate === $date->format('Y-m-d')) {
                $timeOut = Carbon::parse($lastLog->time_out);
            } else {
                return null;
            }
        } else {
            return null;
        }

        if ($this->employeeIsTollCollector($pfNumber)) {
            $counterTimeOut = $this->getTollCollectorTimeoutFromCounter($pfNumber, $timeIn);
            if ($counterTimeOut) {
                return $counterTimeOut;
            }
        }

        return $timeOut;
    }

    /**
     * Send notification for violation
     */
    private function sendNotification(array $violation, string $type): void
    {
        if (!config('attendance.violation_email_enabled')) {
            return;
        }

        try {
            $employeeEmail = $this->getEmailFromPfNumber($violation['pf_number']);
            $approvers = $this->getEmployeeApprovers();
            $subject = ucfirst(str_replace('_', ' ', $type)) . " Notification - {$violation['employee_name']}";
            $html = app(BmsEmailTemplateService::class)->renderAttendanceViolation($violation, $type);
            $plain = $this->buildMessage($violation, $type);
            $payload = array_merge($violation, ['html_body' => $html]);

            if ($employeeEmail) {
                Notifications::pushEmailNotification(
                    $employeeEmail,
                    $subject,
                    $plain,
                    'Attendance Violation',
                    $payload
                );
            }

            foreach ($approvers as $approverId) {
                $approverEmail = $this->getEmailFromUserId($approverId);
                if ($approverEmail) {
                    Notifications::pushEmailNotification(
                        $approverEmail,
                        $subject,
                        $plain,
                        'Attendance Violation',
                        $payload
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to send {$type} notifications", [
                'violation' => $violation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build email message for violation
     */
    private function buildMessage(array $violation, string $type): string
    {
        $isLate = $type === 'late_arrival';
        $violationType = $isLate ? 'late arrival' : 'early departure';
        $timeType = $isLate ? 'Arrival' : 'Departure';
        $minutesField = $isLate ? 'minutes_late' : 'minutes_early';
        $minutesLabel = $isLate ? 'Late' : 'Early';

        return "Dear {$violation['employee_name']},\n\n" .
               "This is to notify you that you have been marked as {$violationType}.\n\n" .
               "Details:\n" .
               "- Employee: {$violation['employee_name']} (PF: {$violation['pf_number']})\n" .
               "- Shift: {$violation['shift_name']}\n" .
               "- Expected {$timeType} Time: " . Carbon::parse($violation['expected_time'])->format('Y-m-d H:i:s') . "\n" .
               "- Actual {$timeType} Time: " . Carbon::parse($violation['actual_time'])->format('Y-m-d H:i:s') . "\n" .
               "- Minutes {$minutesLabel}: {$violation[$minutesField]} minutes\n" .
               "- Date: {$violation['date']}\n\n" .
               "Please ensure you " . ($isLate ? 'arrive on time' : 'complete your full shift duration') . ".\n\n" .
               "Best regards,\n" .
               "Bridge Management System";
    }

    /**
     * Get employee by PF number
     */
    private function getEmployeeByPfNumber(string $pfNumber): ?object
    {
        try {
            return DB::connection('bcmis2')->table('bridge_employee')
                ->where(function($query) use ($pfNumber) {
                    $query->where('pfno', $pfNumber)
                          ->orWhere('pfno2', $pfNumber);
                })
                ->first();
        } catch (\Exception $e) {
            Log::warning('Failed to get employee by PF number', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Format employee name
     */
    private function formatEmployeeName($employee): string
    {
        if (!$employee) {
            return 'Unknown';
        }
        return trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? ''));
    }

    /**
     * Get email from PF number
     */
    private function getEmailFromPfNumber(string $pfNumber): ?string
    {
        try {
            $employee = $this->getEmployeeByPfNumber($pfNumber);
            if (!$employee) {
                return null;
            }

            $user = AuthUser::where(function($query) use ($employee) {
                    $query->where('username', $employee->username ?? '')
                          ->orWhere('email', $employee->email ?? '');
                })
                ->first();

            return $user->email ?? $employee->email ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to get email from PF number', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get email from user ID
     */
    private function getEmailFromUserId(int $userId): ?string
    {
        try {
            $user = AuthUser::find($userId);
            return $user->email ?? null;
        } catch (\Exception $e) {
            Log::warning('Failed to get email from user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get all users with employee approver role
     */
    private function getEmployeeApprovers(): array
    {
        try {
            $role = DB::connection('bcmis2')->table('roles')
                ->whereRaw('LOWER(role_name) = ?', ['employee approver'])
                ->first();

            if (!$role) {
                return [];
            }

            $userIds = [];
            $bridgeEmployeeRoles = BridgeEmployeeRole::where('role_id', $role->id)
                ->active()
                ->get();

            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                $employee = DB::connection('bcmis2')->table('bridge_employee')
                    ->where('national_id', $bridgeEmployeeRole->national_id)
                    ->first();

                if ($employee) {
                    $user = AuthUser::where(function($query) use ($employee) {
                            $query->where('username', $employee->username ?? '')
                                  ->orWhere('email', $employee->email ?? '');
                        })
                        ->first();

                    if ($user) {
                        $userIds[] = $user->id;
                    }
                }
            }

            return array_unique($userIds);
        } catch (\Exception $e) {
            Log::warning('Failed to get employee approvers', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get shift_id from bridge_shift_department based on department_id
     */
    private function getShiftIdFromDepartment(?int $departmentId, Carbon $timeIn): ?int
    {
        if (!$departmentId) {
            return Shift::determineShiftFromTime($timeIn);
        }

        try {
            $shiftDepartments = BridgeShiftDepartment::where('department_id', $departmentId)
                ->where('is_active', true)
                ->get();

            if ($shiftDepartments->isEmpty()) {
                return Shift::determineShiftFromTime($timeIn);
            }

            if ($shiftDepartments->count() === 1) {
                return $shiftDepartments->first()->shift_id;
            }

            $timeInValue = (int) $timeIn->format('Hi');
            $bestMatch = null;
            $minDistance = PHP_INT_MAX;

            foreach ($shiftDepartments as $shiftDept) {
                $shift = BridgeShift::find($shiftDept->shift_id);
                if (!$shift || !$shift->start_time || !$shift->end_time) {
                    continue;
                }

                $startTime = $this->getShiftTimeString($shift->start_time);
                $endTime = $this->getShiftTimeString($shift->end_time);
                
                if (!$startTime || !$endTime) {
                    continue;
                }

                $startTimeValue = (int) str_replace(':', '', $startTime);
                $endTimeValue = (int) str_replace(':', '', $endTime);
                
                $isWithinShift = ($startTimeValue <= $endTimeValue)
                    ? ($timeInValue >= $startTimeValue && $timeInValue <= $endTimeValue)
                    : ($timeInValue >= $startTimeValue || $timeInValue <= $endTimeValue);

                if ($isWithinShift) {
                    return $shift->id;
                }

                $distance = abs($timeInValue - $startTimeValue);
                if ($timeInValue < $startTimeValue && $distance < $minDistance) {
                    $minDistance = $distance;
                    $bestMatch = $shift->id;
                }
            }

            return $bestMatch ?? $shiftDepartments->first()->shift_id;
        } catch (\Exception $e) {
            Log::error('Error getting shift from department', [
                'department_id' => $departmentId,
                'error' => $e->getMessage(),
            ]);
            return Shift::determineShiftFromTime($timeIn);
        }
    }

    /**
     * Check if a bridge shift spans midnight
     */
    private function bridgeShiftSpansMidnight(int $shiftId): bool
    {
        $shift = BridgeShift::find($shiftId);
        if (!$shift || !$shift->start_time || !$shift->end_time) {
            return false;
        }

        $startTimeStr = $this->getShiftTimeString($shift->start_time);
        $endTimeStr = $this->getShiftTimeString($shift->end_time);

        $startParts = explode(':', $startTimeStr);
        $endParts = explode(':', $endTimeStr);
        
        $startValue = (int) ($startParts[0] ?? 0) * 100 + (int) ($startParts[1] ?? 0);
        $endValue = (int) ($endParts[0] ?? 0) * 100 + (int) ($endParts[1] ?? 0);

        return $startValue > $endValue;
    }

    /**
     * Check if an employee has the Toll Collector role
     */
    private function employeeIsTollCollector(string $pfNumber): bool
    {
        try {
            $employee = BridgeEmployee::byPfno($pfNumber)->first();
            if (!$employee) {
                return false;
            }

            $roles = $employee->roles()->where('is_active', true)->get();
            foreach ($roles as $role) {
                if (isset($role->role_name) && strtolower(trim($role->role_name)) === 'toll collector') {
                    return true;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to determine if employee is Toll Collector', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get Toll Collector time_out from bcmis.counter.close_counter
     */
    private function getTollCollectorTimeoutFromCounter(string $pfNumber, Carbon $sessionTimeIn): ?Carbon
    {
        try {
            $authUser = AuthUser::where('pf_number', $pfNumber)->first();
            if (!$authUser) {
                return null;
            }

            $counter = Counter::where('user_id', $authUser->id)
                ->whereDate('open_counter', $sessionTimeIn->toDateString())
                ->whereNotNull('close_counter')
                ->orderByDesc('shift_id')
                ->orderByDesc('close_counter')
                ->first();

            if (!$counter || empty($counter->close_counter)) {
                return null;
            }

            return Carbon::parse($counter->close_counter);
        } catch (\Exception $e) {
            Log::warning('Failed to get Toll Collector timeout from counter', [
                'pf_number' => $pfNumber,
                'session_time_in' => $sessionTimeIn->toDateTimeString(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
