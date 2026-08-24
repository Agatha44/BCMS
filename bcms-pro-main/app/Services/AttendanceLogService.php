<?php

namespace App\Services;

use App\Models\Bms\AttendanceLog;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\PublicHoliday;
use App\Models\Bms\SpecialTask;
use App\Models\AuthUser;
use App\Models\Counter;
use App\Services\AttendanceViolationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Jmrashed\Zkteco\Lib\ZKTeco;

class AttendanceLogService
{
    const TYPE_TIME_IN = 0;
    const TYPE_TIME_OUT = 1;
    const STANDARD_WORK_MINUTES = 540; // 9 hours

    protected string $deviceIp;
    protected int $devicePort;
    protected ZKTeco|false|null $zk = null;

    public function __construct()
    {
        $this->deviceIp = config('attendance.device_ip');
        $this->devicePort = config('attendance.device_port');
        $this->ensureZktecoVendorLogDirectory();
    }

    /**
     * jmrashed/zkteco writes to vendor/.../Lib/logs/error.log; sync fails if that folder is missing.
     */
    protected function ensureZktecoVendorLogDirectory(): void
    {
        foreach ([
            base_path('vendor/jmrashed/zkteco/src/Lib/logs'),
            base_path('vendor/jmrashed/zkteco/src/Lib/Helper/logs'),
        ] as $logDir) {
            if (is_dir($logDir)) {
                continue;
            }

            if (@mkdir($logDir, 0775, true) || is_dir($logDir)) {
                continue;
            }

            Log::warning('Unable to create ZKTeco vendor log directory', ['path' => $logDir]);
        }
    }

    /**
     * Get ZKTeco instance
     */
    protected function getZKTeco(): ?ZKTeco
    {
        if ($this->zk !== null) {
            return $this->zk ?: null;
        }

        if (!function_exists('socket_create')) {
            Log::error('PHP sockets extension not enabled');
            return $this->zk = false;
        }

        try {
            return $this->zk = new ZKTeco($this->deviceIp, $this->devicePort);
        } catch (\Throwable $e) {
            Log::error('ZKTeco init failed: ' . $e->getMessage());
            return $this->zk = false;
        }
    }

    public function connect(): bool
    {
        return $this->getZKTeco()?->connect() ?? false;
    }

    public function disconnect(): bool
    {
        return $this->getZKTeco()?->disconnect() ?? false;
    }

    /**
     * Execute device operation with automatic connection handling
     */
    protected function executeDeviceOperation(callable $operation): array
    {
        if (!$this->connect()) {
            return ['success' => false, 'error' => 'Device connection failed'];
        }

        try {
            $result = $operation($this->getZKTeco());
            $this->disconnect();
            return $result;
        } catch (\Throwable $e) {
            $this->disconnect();
            Log::error('Device operation failed: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get all attendance logs from device
     */
    public function getAttendanceLogs(): array
    {
        return $this->executeDeviceOperation(function ($zk) {
            $zk->disableDevice();
            $logs = $zk->getAttendance();
            $zk->enableDevice();

            return [
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
            ];
        });
    }

    /**
     * Get recent attendance logs from device
     */
    public function getRecentAttendanceLogs(int $minutes = 5): array
    {
        $result = $this->getAttendanceLogs();
        if (!$result['success']) {
            return $result;
        }

        $cutoff = now()->subMinutes($minutes)->timestamp;
        $logs = array_filter($result['logs'], fn($log) => strtotime($log['timestamp']) >= $cutoff);

        return [
            'success' => true,
            'logs' => array_values($logs),
            'count' => count($logs),
        ];
    }

    /**
     * Get device time
     */
    public function getDeviceTime(): array
    {
        return $this->executeDeviceOperation(function ($zk) {
            return ['success' => true, 'time' => $zk->getTime()];
        });
    }

    /**
     * Get device version
     */
    public function getDeviceVersion(): array
    {
        return $this->executeDeviceOperation(function ($zk) {
            return ['success' => true, 'version' => $zk->version()];
        });
    }

    /**
     * Convert device logs to attendance sessions
     */
    public function convertDeviceLogsToSessions(array $deviceLogs): array
    {
        $grouped = [];
        $unknownStates = [];

        foreach ($deviceLogs as $log) {
            $userId = $log['id'] ?? $log['uid'] ?? null;
            if (!$userId) {
                continue;
            }

            $time = Carbon::parse($log['timestamp']);
            $date = $time->toDateString();
            $attendanceType = $this->identifyAttendanceTypeFromLog($log);

            if ($attendanceType === null) {
                $unknownKey = sprintf(
                    'state:%s|type:%s|punch:%s',
                    $log['state'] ?? 'null',
                    $log['type'] ?? 'null',
                    $log['punch'] ?? 'null'
                );
                $unknownStates[$unknownKey] = ($unknownStates[$unknownKey] ?? 0) + 1;
                continue;
            }

            $grouped[$userId][$date][$attendanceType][] = $time;
        }

        if (!empty($unknownStates)) {
            Log::warning('Unknown attendance type values encountered', [
                'unknown_values' => $unknownStates,
                'known_types' => [self::TYPE_TIME_IN => 'TIME_IN', self::TYPE_TIME_OUT => 'TIME_OUT']
            ]);
        }

        $sessions = [];
        foreach ($grouped as $userId => $dates) {
            $pfNumber = $this->transformUserIdToPfNumber($userId);

            foreach ($dates as $date => $data) {
                $data['out'] = $data['out'] ?? [];
                $data['in'] = $data['in'] ?? [];
                sort($data['in']);
                sort($data['out']);

                if (empty($data['in']) && empty($data['out'])) {
                    continue;
                }

                if (empty($data['in']) && !empty($data['out'])) {
                    $sessions[] = [
                        'device_user_id' => $userId,
                        'pf_number' => $pfNumber,
                        'date' => $date,
                        'time_in' => null,
                        'time_out' => end($data['out']),
                        'status' => 'completed',
                        'out_only' => true,
                    ];
                    continue;
                }

                $sessions[] = [
                    'device_user_id' => $userId,
                    'pf_number' => $pfNumber,
                    'date' => $date,
                    'time_in' => $data['in'][0],
                    'time_out' => !empty($data['out']) ? end($data['out']) : null,
                    'status' => !empty($data['out']) ? 'completed' : 'active',
                ];
            }
        }

        return $sessions;
    }

    /**
     * Map a device punch to in/out.
     *
     * jmrashed/zkteco: "type" is punch direction (0=in, 1=out). "state" is verify mode (fingerprint, face, etc.).
     * Overtime is derived later from time_in/time_out via calculateOvertimeStatus(), not from device punch types.
     */
    protected function identifyAttendanceTypeFromLog(array $log): ?string
    {
        foreach (['type', 'punch'] as $field) {
            if (!array_key_exists($field, $log) || $log[$field] === null || $log[$field] === '') {
                continue;
            }

            $mapped = match ((int) $log[$field]) {
                self::TYPE_TIME_IN => 'in',
                self::TYPE_TIME_OUT => 'out',
                default => null,
            };

            if ($mapped !== null) {
                return $mapped;
            }
        }

        return null;
    }

    /**
     * Transform device user ID to PF number format
     */
    protected function transformUserIdToPfNumber($userid): ?string
    {
        if ($userid === null || $userid === '') {
            return null;
        }

        $useridStr = trim((string) $userid);

        if (preg_match('/^NB\d+$/i', $useridStr)) {
            return strtoupper($useridStr);
        }

        if (!is_numeric($useridStr)) {
            Log::warning('Invalid userid format for PF transformation', ['userid' => $userid]);
            return null;
        }

        $numericId = (int) $useridStr;
        if ($numericId <= 0) {
            Log::warning('Invalid userid value for PF transformation', ['userid' => $userid]);
            return null;
        }

        return 'NB' . str_pad($numericId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if employee has Toll Collector role
     */
    protected function employeeIsTollCollector(string $pfNumber): bool
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
        } catch (\Throwable $e) {
            Log::warning('Failed to determine if employee is Toll Collector', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Get Toll Collector time_out from counter table
     */
    protected function getTollCollectorTimeoutFromCounter(string $pfNumber, Carbon $sessionTimeIn): ?Carbon
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
        } catch (\Throwable $e) {
            Log::warning('Failed to get Toll Collector timeout from counter', [
                'pf_number' => $pfNumber,
                'session_time_in' => $sessionTimeIn->toDateTimeString(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calculate overtime status considering public holidays and special tasks
     */
    protected function calculateOvertimeStatus(?Carbon $timeIn, ?Carbon $timeOut, ?string $pfNumber = null): string
    {
        if (!$timeIn || !$timeOut) {
            return 'Normal';
        }

        $date = $timeIn->format('Y-m-d');
        $totalWorkedMinutes = $timeIn->diffInMinutes($timeOut);
        $totalWorkedHours = $totalWorkedMinutes / 60;

        // Check if it's a public holiday
        $isHoliday = PublicHoliday::isHoliday($date);

        // Check if employee has special task (only if pfNumber is provided)
        $specialTask = $pfNumber ? SpecialTask::getTaskForDate($pfNumber, $date) : null;

        // Priority: Special Task > Holiday > Regular
        if ($specialTask) {
            // Special task: apply task-specific overtime rules
            switch ($specialTask->overtime_rule) {
                case 'all_hours':
                    // All hours worked count as overtime
                    return $totalWorkedHours > 0 ? 'Overtime' : 'Normal';

                case 'custom':
                    // Custom threshold
                    $threshold = $specialTask->custom_overtime_threshold ?? (self::STANDARD_WORK_MINUTES / 60);
                    $overtimeHours = $totalWorkedHours - $threshold;
                    return $overtimeHours > 0 ? 'Overtime' : 'Normal';

                case 'standard':
                default:
                    // Standard 9-hour rule
                    $overtimeMinutes = $totalWorkedMinutes - self::STANDARD_WORK_MINUTES;
                    return $overtimeMinutes > 0 ? 'Overtime' : 'Normal';
            }
        } elseif ($isHoliday) {
            // Public holiday: all hours count as overtime
            return $totalWorkedHours > 0 ? 'Overtime' : 'Normal';
        } else {
            // Regular overtime: hours worked - 9 hours
            $overtimeMinutes = $totalWorkedMinutes - self::STANDARD_WORK_MINUTES;
            return $overtimeMinutes > 0 ? 'Overtime' : 'Normal';
        }
    }

    /**
     * Process and sync attendance sessions to database
     */
    public function processSessions(array $sessions, bool $logDetails = true): array
    {
        $syncedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $skippedReasons = [];
        $errors = [];

        DB::connection('bcmis2')->beginTransaction();

        try {
            foreach ($sessions as $index => $session) {
                try {
                    $pfNumber = $session['pf_number'] ?? null;
                    if (!$pfNumber && isset($session['device_user_id'])) {
                        $pfNumber = $this->transformUserIdToPfNumber($session['device_user_id']);
                    }

                    if (!$pfNumber) {
                        $reason = "PF number not found in session data and could not be transformed from device_user_id";
                        if ($logDetails) {
                            Log::warning('PF number not found in session', [
                                'session_index' => $index,
                                'device_user_id' => $session['device_user_id'] ?? null,
                            ]);
                        }
                        $skippedReasons[] = $reason;
                        $skippedCount++;
                        continue;
                    }

                    $sessionDate = ($session['time_in'] ?? $session['time_out'])?->format('Y-m-d')
                        ?? ($session['date'] ?? null);
                    if (!$sessionDate) {
                        $skippedReasons[] = 'Session missing time_in/time_out date';
                        $skippedCount++;
                        continue;
                    }

                    $existingRecord = AttendanceLog::where('pf_number', $pfNumber)
                        ->whereDate('time_in', $sessionDate)
                        ->first();

                    if (!empty($session['out_only'])) {
                        if (!$existingRecord) {
                            $skippedReasons[] = "Check-out punch with no existing session for {$pfNumber} on {$sessionDate}";
                            $skippedCount++;
                            continue;
                        }

                        $session['time_in'] = Carbon::parse($existingRecord->time_in);
                    }

                    if ($this->employeeIsTollCollector($pfNumber) && $session['time_in']) {
                        $tollCollectorTimeout = $this->getTollCollectorTimeoutFromCounter($pfNumber, $session['time_in']);
                        if ($tollCollectorTimeout) {
                            $session['time_out'] = $tollCollectorTimeout;
                            $session['status'] = 'completed';
                        }
                    }

                    $timeOut = $session['time_out'];
                    $status = $session['status'] ?? ($timeOut ? 'completed' : 'active');

                    if ($existingRecord && $timeOut === null && $existingRecord->time_out) {
                        $timeOut = Carbon::parse($existingRecord->time_out);
                        $status = $existingRecord->status ?? 'completed';
                    }

                    $sessionDuration = ($session['time_in'] && $timeOut)
                        ? $session['time_in']->diffInMinutes($timeOut)
                        : null;

                    $updateData = [
                        'pf_number' => $pfNumber,
                        'ip_address' => config('attendance.device_ip'),
                        'time_in' => $session['time_in'],
                        'time_out' => $timeOut,
                        'status' => $status,
                        'session_duration' => $sessionDuration,
                        'overtime_status' => $this->calculateOvertimeStatus($session['time_in'], $timeOut, $pfNumber),
                    ];

                    if ($existingRecord) {
                        $existingRecord->update($updateData);
                        $syncedLog = $existingRecord->fresh();
                    } else {
                        $syncedLog = AttendanceLog::create($updateData);
                    }

                    $syncedCount++;

                    if ($logDetails) {
                        Log::info('Attendance session synced', [
                            'pf_number' => $pfNumber,
                            'log_id' => $syncedLog->id,
                            'action' => $existingRecord ? 'updated' : 'created',
                        ]);
                    }

                    if ($syncedLog && $syncedLog->id) {
                        try {
                            (new AttendanceViolationService())->checkViolationsForLog($syncedLog);
                        } catch (\Exception $e) {
                            Log::error('Error checking violations for synced log', [
                                'log_id' => $syncedLog->id,
                                'pf_number' => $pfNumber,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    if ($logDetails) {
                        Log::error('Error syncing attendance session', [
                            'session_index' => $index,
                            'error' => $e->getMessage(),
                        ]);
                    }
                    $errors[] = "Error processing session: " . $e->getMessage();
                    $errorCount++;
                }
            }

            DB::connection('bcmis2')->commit();
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            throw $e;
        }

        return [
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'skipped_reasons' => $skippedReasons,
            'errors_list' => $errors,
        ];
    }

    /**
     * Sync attendance logs from device
     */
    public function syncAllLogs(bool $logDetails = false): array
    {
        return $this->syncLogs($this->getAttendanceLogs(), $logDetails, 'All attendance logs synced successfully');
    }

    /**
     * Sync recent attendance logs from device
     */
    public function syncRecentLogs(int $minutesLookBack = 5, bool $logDetails = false): array
    {
        return $this->syncLogs(
            $this->getRecentAttendanceLogs($minutesLookBack),
            $logDetails,
            'Attendance logs synced successfully'
        );
    }

    /**
     * Common sync logic for all and recent logs
     */
    private function syncLogs(array $result, bool $logDetails, string $successMessage): array
    {
        try {
            if (!$result['success']) {
                return $this->buildErrorResponse($result['error'] ?? 'Failed to fetch logs from device');
            }

            if ($result['count'] == 0) {
                return $this->buildSuccessResponse(0, 0, 0, 'No attendance logs found on device');
            }

            $sessions = $this->convertDeviceLogsToSessions($result['logs']);

            if (empty($sessions)) {
                return $this->buildSuccessResponse(0, 0, 0, 'No sessions created from device logs');
            }

            $syncResult = $this->processSessions($sessions, $logDetails);

            return [
                'success' => true,
                'message' => $successMessage,
                'synced' => $syncResult['synced'],
                'skipped' => $syncResult['skipped'],
                'errors' => $syncResult['errors'],
                'total_sessions' => count($sessions),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to sync attendance logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Build success response
     */
    private function buildSuccessResponse(int $synced, int $skipped, int $errors, string $message): array
    {
        return [
            'success' => true,
            'message' => $message,
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Build error response
     */
    private function buildErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'synced' => 0,
            'skipped' => 0,
            'errors' => 1,
        ];
    }
}
