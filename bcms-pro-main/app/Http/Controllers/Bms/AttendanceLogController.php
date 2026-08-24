<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\AttendanceLog;
use App\Models\AuthUser;
use App\Models\AuthUserRole;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Services\Overtime\OvertimeDayLockService;
use App\Services\AttendanceLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Controller for managing attendance logs from fingerprint devices
 *
 * Note: Attendance logs are primarily written directly to the database by fingerprint devices
 * when users sign in and sign out. This controller provides viewing, calculation, and management
 * functionality for those logs.
 */
class AttendanceLogController extends Controller
{
    /**
     * Cache for employee data to avoid N+1 queries
     *
     * @var array
     */
    private $employeeCache = [];
    /**
     * Check if current user has supervisor privileges
     *
     * NOTE: Supervisor check removed - authorization is now controlled on the frontend
     *
     * @param int $userId
     * @return bool
     */
    private function isSupervisor($userId = null)
    {
        // Supervisor check removed - always return true
        // Authorization is now controlled on the frontend
        return true;
    }

    /**
     * Get current user's PF number from bridge_employee table
     *
     * @return string|null
     */
    private function getCurrentUserPfNumber()
    {
        $currentUserId = auth()->id();
        if (!$currentUserId) {
            return null;
        }

        try {
            $user = AuthUser::find($currentUserId);
            
            if (!$user || empty($user->pf_number)) {
                return null;
            }
            return $user->pf_number;
        } catch (\Exception $e) {
            Log::warning('Failed to get PF number for user', [
                'user_id' => $currentUserId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Check if user can view attendance logs (own or as supervisor)
     *
     * NOTE: Authorization check removed - authorization is now controlled on the frontend
     *
     * @param string $targetPfNumber
     * @return bool
     */
    private function canViewAttendance($targetPfNumber)
    {
        // Authorization check removed - always return true
        // Authorization is now controlled on the frontend
        return true;
    }

    /**
     * Get attendance list (index endpoint)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // If pf_number is provided, show specific employee's attendance
            if ($request->has('pf_number')) {
                return $this->getUserSessions($request);
            }

            // Return all sessions (authorization controlled on frontend)
            return $this->getAllSessions($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee's attendance sessions from biometric device logs
     *
     * Employees can view their own sessions. Supervisors can view any employee's sessions.
     * Supports multiple sessions per day as recorded by the biometric device.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function getUserSessions(Request $request, $pfNumber = null)
    {
        try {
            $targetPfNumber = $this->getCurrentUserPfNumber();

            if (!$targetPfNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'PF number is required',
                ], 400);
            }

            // Authorization check removed - controlled on frontend

            // Get employee information
            $employee = BridgeEmployee::byPfno($targetPfNumber)->first();

            $employeeName = $employee
                ? trim("{$employee->fname} {$employee->mname} {$employee->sname}")
                : 'Unknown Employee';

            $query = DB::table('bcmis2.attendance_logs as al')
                ->where('al.pf_number', $targetPfNumber)
                ->select('al.*')
                ->orderBy('al.time_in', 'desc');

            // Date filters
            if ($request->filled('start_date')) {
                $query->whereDate('al.time_in', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('al.time_in', '<=', $request->end_date);
            }

            // Status filter
            if ($request->filled('status')) {
                $query->where('al.status', $request->status);
            }

            // Active sessions only
            if ($request->boolean('active_only')) {
                $query->whereNull('al.time_out');
            }

            $perPage = $request->per_page ?? 15;
            $sessions = $query->paginate($perPage);

            // Transform sessions
            $transformedSessions = $sessions->getCollection()->map(function ($session) {
                return $this->transformSession($session);
            });

            // Summary calculation
            $allUserSessions = AttendanceLog::where('pf_number', $targetPfNumber)
                ->when($request->filled('start_date'), fn ($q) =>
                $q->whereDate('time_in', '>=', $request->start_date)
                )
                ->when($request->filled('end_date'), fn ($q) =>
                $q->whereDate('time_in', '<=', $request->end_date)
                )
                ->get();

            $totalMinutes = 0;
            foreach ($allUserSessions as $session) {
                if ($session->time_out) {
                    $totalMinutes += $session->session_duration
                        ?? Carbon::parse($session->time_in)->diffInMinutes(Carbon::parse($session->time_out));
                }
            }

            return response()->json([
                'success' => true,
                'employee' => [
                    'pf_number' => $targetPfNumber,
                    'name' => $employeeName,
                ],
                'summary' => [
                    'total_sessions' => $allUserSessions->count(),
                    'total_hours' => round($totalMinutes / 60, 2),
                    'total_minutes' => $totalMinutes,
                ],
                'data' => $transformedSessions,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all attendance sessions (supervisor view)
     *
     * Returns all attendance sessions with optional filtering.
     * Supports filtering by pf_number, date range, status, and search.
     *
     * Query Parameters:
     * - pf_number: Filter by specific PF number (optional)
     * - start_date: Start date for filtering (Y-m-d format, optional)
     * - end_date: End date for filtering (Y-m-d format, optional)
     * - status: Filter by status (optional)
     * - active_only: Show only active sessions (true/false, optional)
     * - search: Search by pf_number or ip_address (optional)
     * - per_page: Number of records per page (default: 50, optional)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSessions(Request $request)
    {
        try {

            $query = DB::table('bcmis2.attendance_logs as al')
                ->leftJoin('bcmis2.bridge_employee as be', function($join) {
                    $join->on('al.pf_number', '=', 'be.pfno')
                         ->orOn('al.pf_number', '=', 'be.pfno2');
                })
                ->select('al.*')
                ->orderBy('al.time_in', 'desc');

            // Filter by pf_number
            if ($request->has('pf_number')) {
                $query->where('al.pf_number', $request->pf_number);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('al.time_in', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('al.time_in', '<=', $request->end_date);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('al.status', $request->status);
            }

            // Filter by active sessions only
            if ($request->has('active_only') && $request->active_only) {
                $query->whereNull('al.time_out');
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('al.pf_number', 'like', "%{$search}%")
                        ->orWhere('al.ip_address', 'like', "%{$search}%")
                        ->orWhere('be.fname', 'like', "%{$search}%")
                        ->orWhere('be.mname', 'like', "%{$search}%")
                        ->orWhere('be.sname', 'like', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(COALESCE(be.fname, ''), ' ', COALESCE(be.mname, ''), ' ', COALESCE(be.sname, ''))"), 'like', "%{$search}%");
                });
            }

            $perPage = $request->per_page ?? 15;
            $sessions = $query->paginate($perPage);

            // Transform data
            $transformedSessions = $sessions->getCollection()->map(function ($session) {
                return $this->transformSession($session);
            });

            return response()->json([
                'success' => true,
                'data' => $transformedSessions,
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'last_page' => $sessions->lastPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'from' => $sessions->firstItem(),
                    'to' => $sessions->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance management summary
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendanceManagement(Request $request)
    {
        try {
            // Supervisor check removed - authorization controlled on frontend
            $startDate = $request->start_date ?? Carbon::today()->startOfMonth();
            $endDate = $request->end_date ?? Carbon::today()->endOfDay();

            // Get summary statistics
            $totalSessions = AttendanceLog::whereDate('time_in', '>=', $startDate)
                ->whereDate('time_in', '<=', $endDate)
                ->count();

            $activeSessions = AttendanceLog::whereNull('time_out')
                ->whereDate('time_in', '>=', $startDate)
                ->whereDate('time_in', '<=', $endDate)
                ->count();

            $completedSessions = AttendanceLog::whereNotNull('time_out')
                ->whereDate('time_in', '>=', $startDate)
                ->whereDate('time_in', '<=', $endDate)
                ->count();

            // Get unique employees count
            $uniqueEmployees = AttendanceLog::whereDate('time_in', '>=', $startDate)
                ->whereDate('time_in', '<=', $endDate)
                ->distinct('pf_number')
                ->count('pf_number');

            // Get today's attendance
            $todaySessions = AttendanceLog::whereDate('time_in', Carbon::today())
                ->orderBy('time_in', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($session) {
                    return $this->transformSession($session);
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_sessions' => $totalSessions,
                        'active_sessions' => $activeSessions,
                        'completed_sessions' => $completedSessions,
                        'unique_employees' => $uniqueEmployees,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'today_sessions' => $todaySessions,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance management data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get attendance by ID or by PF number
     *
     * If $id is numeric, returns a specific attendance session.
     * If $id is 'employee' or request has pf_number parameter, returns all sessions for that employee.
     *
     * @param Request $request
     * @param int|string $id Attendance session ID or 'employee' keyword
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id = null)
    {
        try {
            // If pf_number is provided in request, show all sessions for that employee
            if ($request->has('pf_number') || $id === 'employee') {
                $pfNumber = $request->pf_number ?? $id;

                // Authorization check removed - controlled on frontend
                return $this->getUserSessions($request, $pfNumber);
            }

            // Otherwise, treat $id as attendance session ID
            if (!$id || !is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid attendance session ID or PF number parameter required',
                ], 400);
            }

            $session = DB::table('bcmis2.attendance_logs as al')
                ->where('al.id', $id)
                ->select('al.*')
                ->first();

            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance session not found',
                ], 404);
            }

            // Authorization check removed - controlled on frontend
            return response()->json([
                'success' => true,
                'data' => $this->transformSession($session),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transform session data for response
     *
     * @param AttendanceLog $session
     * @return array
     */
    private function transformSession($session)
    {
        $hoursWorked = 0;
        $minutesWorked = 0;

        if ($session->time_out) {
            $minutesWorked = $session->session_duration
                ?? Carbon::parse($session->time_in)->diffInMinutes(Carbon::parse($session->time_out));
            $hoursWorked = round($minutesWorked / 60, 2);
        }

        // Get employee information (with caching to avoid N+1 queries)
        $employeeName = null;
        $userId = null;

        if ($session->pf_number) {
            // Check cache first
            if (!isset($this->employeeCache[$session->pf_number])) {
                try {
                    $employee = BridgeEmployee::byPfno($session->pf_number)->first();

                    if ($employee) {
                        // Get employee name
                        $employeeName = trim("{$employee->fname} {$employee->mname} {$employee->sname}");

                        // Get user ID from AuthUser by matching username or email
                        $userId = null;
                        if ($employee->username || $employee->email) {
                            $user = AuthUser::where(function($query) use ($employee) {
                                if ($employee->username) {
                                    $query->where('username', $employee->username);
                                }
                                if ($employee->email) {
                                    $query->orWhere('email', $employee->email);
                                }
                            })->first();

                            if ($user) {
                                $userId = $user->id;
                            }
                        }

                        // Cache the result
                        $this->employeeCache[$session->pf_number] = [
                            'name' => $employeeName,
                            'userId' => $userId
                        ];
                    } else {
                        // Cache null result to avoid repeated queries
                        $this->employeeCache[$session->pf_number] = [
                            'name' => null,
                            'userId' => null
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get employee information for attendance session', [
                        'pf_number' => $session->pf_number,
                        'session_id' => $session->id,
                        'error' => $e->getMessage()
                    ]);
                    // Cache null result on error
                    $this->employeeCache[$session->pf_number] = [
                        'name' => null,
                        'userId' => null
                    ];
                }
            }

            // Get from cache
            $cached = $this->employeeCache[$session->pf_number];
            $employeeName = $cached['name'];
            $userId = $cached['userId'];
        }

        // Calculate overtime status if not already set (for backward compatibility with existing records)
        $overtimeStatus = $session->overtime_status ?? null;
        if (!$overtimeStatus && $session->time_out) {
            // Calculate on-the-fly for existing records that don't have overtime_status set
            $totalWorkedMinutes = Carbon::parse($session->time_in)->diffInMinutes(Carbon::parse($session->time_out));
            $overtimeMinutes = $totalWorkedMinutes - 540; // 9 hours = 540 minutes
            $overtimeStatus = $overtimeMinutes > 0 ? 'Overtime' : 'Normal';
        } elseif (!$overtimeStatus) {
            $overtimeStatus = 'Normal'; // Default to Normal if no time_out
        }

        return [
            'id' => $session->id,
            'pf_number' => $session->pf_number,
            'employeeId' => $session->pf_number,
            'employeeName' => $employeeName,
            'userId' => $userId,
            'ip_address' => $session->ip_address,
            'time_in' => $session->time_in,
            'time_out' => $session->time_out,
            'status' => $session->status,
            'session_duration' => $session->session_duration ?? $minutesWorked,
            'hours_worked' => $hoursWorked,
            'minutes_worked' => $minutesWorked,
            'overtime_status' => $overtimeStatus,
            'is_late_arrival' => (bool) ($session->is_late_arrival ?? false),
            'is_early_departure' => (bool) ($session->is_early_departure ?? false),
        ];
    }

    /**
     * Get attendance record by date for the logged in user
     *
     * Returns all fields from the attendance log for the authenticated user on the specified date.
     * Body: date (required), returned_overtime_request_id (optional) — when resubmitting a Returned
     * overtime request, pass its id so days on that request remain selectable.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByDate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'returned_overtime_request_id' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $pfNumber = $this->getCurrentUserPfNumber();

            if (!$pfNumber) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or PF number not found',
                ], 401);
            }

            $date = $request->date;

            if ($pfNumber) {
                $returnedOvertimeRequestId = $request->filled('returned_overtime_request_id')
                    ? (int) $request->returned_overtime_request_id
                    : null;

                $dayLockService = app(OvertimeDayLockService::class);

                if ($dayLockService->isDayBlocked($pfNumber, $date, $returnedOvertimeRequestId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This date has already been submitted in an overtime request and cannot be submitted again.',
                        'data' => null,
                    ], 400);
                }
            }

            // Get attendance record for the employee on the specified date
            $attendance = DB::table('bcmis2.attendance_logs as al')
                ->where('al.pf_number', $pfNumber)
                ->whereDate('al.time_in', $date)
                ->select('al.*')
                ->first();

            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attendance record found for the specified date',
                    'data' => null,
                ], 404);
            }

            // Check if time_out is null - no sign out records means no overtime
            if (is_null($attendance->time_out) || empty($attendance->time_out)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No overtime for this day',
                    'data' => null,
                ], 200);
            }

            // Calculate overtime status if not already set (for backward compatibility)
            $overtimeStatus = $attendance->overtime_status ?? null;
            if (!$overtimeStatus && $attendance->time_out) {
                // Calculate on-the-fly for existing records that don't have overtime_status set
                $totalWorkedMinutes = Carbon::parse($attendance->time_in)->diffInMinutes(Carbon::parse($attendance->time_out));
                $overtimeMinutes = $totalWorkedMinutes - 540; // 9 hours = 540 minutes
                $overtimeStatus = $overtimeMinutes > 0 ? 'Overtime' : 'Normal';
            } elseif (!$overtimeStatus) {
                $overtimeStatus = 'Normal'; // Default to Normal if no time_out
            }

            // Return all fields from the attendance log
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $attendance->id,
                    'pf_number' => $attendance->pf_number,
                    'ip_address' => $attendance->ip_address,
                    'time_in' => $attendance->time_in,
                    'time_out' => $attendance->time_out,
                    'status' => $attendance->status,
                    'session_duration' => $attendance->session_duration,
                    'overtime_status' => $overtimeStatus,
                    'is_late_arrival' => (bool) ($attendance->is_late_arrival ?? false),
                    'is_early_departure' => (bool) ($attendance->is_early_departure ?? false),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance record: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Fetch attendance logs from ZKTeco device (without saving)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchDeviceLogs()
    {
        try {
            $attendanceService = new AttendanceLogService();
            $result = $attendanceService->getAttendanceLogs();

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fetch logs from device'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'count' => $result['count'],
                'logs' => $result['logs']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch device logs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync attendance logs from ZKTeco device to database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncDeviceLogs(Request $request)
    {
        $request->validate([
            'clear_device' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date'
        ]);

        try {
            $attendanceService = new AttendanceLogService();

            // Get logs from device
            if ($request->has('start_date') && $request->has('end_date')) {
                $result = $attendanceService->getAttendanceLogsByDateRange(
                    $request->start_date,
                    $request->end_date
                );
            } else {
                $result = $attendanceService->getAttendanceLogs();
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to get logs from device'
                ], 500);
            }

            Log::info('Attendance sync started', [
                'device_logs_count' => $result['count'] ?? 0,
                'has_logs' => !empty($result['logs'])
            ]);

            // Convert device logs to sessions
            $sessions = $attendanceService->convertDeviceLogsToSessions($result['logs']);

            Log::info('Device logs converted to sessions', [
                'device_logs_count' => $result['count'] ?? 0,
                'sessions_count' => count($sessions)
            ]);

            if (empty($sessions)) {
                Log::warning('No sessions created from device logs', [
                    'device_logs_count' => $result['count'] ?? 0,
                    'device_logs_sample' => array_slice($result['logs'] ?? [], 0, 3)
                ]);
            }

            // Process sessions using the service
            $attendanceService = new AttendanceLogService();
            $result = $attendanceService->processSessions($sessions, true);
            $syncedCount = $result['synced'];
            $skippedCount = $result['skipped'];
            $errorCount = $result['errors'];
            $skippedReasons = $result['skipped_reasons'];
            $errors = $result['errors_list'];

            // Clear device logs if requested
            if ($request->boolean('clear_device') && config('attendance.auto_clear_logs', false)) {
                $clearResult = $attendanceService->clearAttendanceLogs();
                if (!$clearResult['success']) {
                    Log::warning('Failed to clear device logs', [
                        'error' => $clearResult['error'] ?? 'Unknown error'
                    ]);
                }
            }

            $message = 'Attendance logs synced successfully. Existing records were updated based on pf_number and date.';
            if ($syncedCount === 0 && $skippedCount > 0) {
                $message = 'No attendance logs were synced. All logs were skipped (PF number not found in session data).';
            } elseif ($syncedCount === 0 && count($sessions) === 0) {
                $message = 'No attendance logs found on device to sync.';
            }

            $response = [
                'success' => true,
                'message' => $message,
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_device_logs' => $result['count'],
                'total_sessions' => count($sessions)
            ];

            // Include skipped reasons if there are any (limit to first 10 for response size)
            if (!empty($skippedReasons) && $skippedCount > 0) {
                $response['skipped_reasons_sample'] = array_slice($skippedReasons, 0, 10);
                if ($skippedCount > 10) {
                    $response['skipped_reasons_sample'][] = "... and " . ($skippedCount - 10) . " more";
                }
            }

            // Include errors if there are any (limit to first 10 for response size)
            if (!empty($errors) && $errorCount > 0) {
                $response['error_details_sample'] = array_slice($errors, 0, 10);
                if ($errorCount > 10) {
                    $response['error_details_sample'][] = "... and " . ($errorCount - 10) . " more errors";
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();

            Log::error('Attendance sync failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage(),
                'message' => 'An error occurred during sync. Check logs for details.'
            ], 500);
        }
    }

    /**
     * Get device information
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeviceInfo()
    {
        try {
            $attendanceService = new AttendanceLogService();
            $version = $attendanceService->getDeviceVersion();
            $time = $attendanceService->getDeviceTime();

            return response()->json([
                'success' => true,
                'version' => $version['version'] ?? null,
                'device_time' => $time['time'] ?? null,
                'device_ip' => config('attendance.device_ip'),
                'device_port' => config('attendance.device_port'),
                'version_error' => $version['error'] ?? null,
                'time_error' => $time['error'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get device info: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick sync recent attendance logs (optimized for auto-sync during signing)
     *
     * This endpoint syncs only recent logs (last N minutes) for faster response.
     * Useful for triggering immediate sync after signing or for frequent polling.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function quickSyncRecentLogs(Request $request)
    {
        $request->validate([
            'minutes' => 'integer|min:1|max:60'
        ]);

        try {
            $attendanceService = new AttendanceLogService();

            // Get minutes from request or config
            $minutes = $request->input('minutes', config('attendance.recent_logs_window', 10));

            // Get recent logs from device
            $result = $attendanceService->getRecentAttendanceLogs($minutes);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to get logs from device'
                ], 500);
            }

            if ($result['count'] == 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No new logs to sync',
                    'synced' => 0,
                    'skipped' => 0,
                    'total_logs' => 0
                ]);
            }

            Log::info('Quick sync started', [
                'recent_logs_count' => $result['count'],
                'minutes' => $minutes,
                'cutoff_time' => $result['cutoff_time'] ?? null
            ]);

            // Convert device logs to sessions
            $sessions = $attendanceService->convertDeviceLogsToSessions($result['logs']);

            if (empty($sessions)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No sessions to sync',
                    'synced' => 0,
                    'skipped' => 0,
                    'total_logs' => $result['count']
                ]);
            }

            // Process sessions using the service (with minimal logging for quick sync)
            $attendanceService = new AttendanceLogService();
            $result = $attendanceService->processSessions($sessions, false);
            $syncedCount = $result['synced'];
            $skippedCount = $result['skipped'];
            $errorCount = $result['errors'];
            $skippedReasons = $result['skipped_reasons'];
            $errors = $result['errors_list'];

            // Update last sync timestamp
            cache()->put(config('attendance.last_sync_cache_key'), now()->toDateTimeString());

            $message = 'Quick sync completed successfully. Existing records were updated based on pf_number and date.';
            if ($syncedCount === 0 && $skippedCount > 0) {
                $message = 'No logs were synced. All logs were skipped (PF number not found in session data).';
            } elseif ($syncedCount === 0 && count($sessions) === 0) {
                $message = 'No attendance logs found to sync.';
            }

            $response = [
                'success' => true,
                'message' => $message,
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_device_logs' => $result['count'],
                'total_sessions' => count($sessions),
                'minutes_looked_back' => $minutes,
                'cutoff_time' => $result['cutoff_time'] ?? null
            ];

            // Include skipped reasons if there are any (limit to first 5 for response size)
            if (!empty($skippedReasons) && $skippedCount > 0) {
                $response['skipped_reasons_sample'] = array_slice($skippedReasons, 0, 5);
            }

            // Include errors if there are any (limit to first 5 for response size)
            if (!empty($errors) && $errorCount > 0) {
                $response['error_details_sample'] = array_slice($errors, 0, 5);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();

            Log::error('Quick sync failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Quick sync failed: ' . $e->getMessage(),
                'message' => 'An error occurred during quick sync. Check logs for details.'
            ], 500);
        }
    }

}
