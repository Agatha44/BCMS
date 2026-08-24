<?php

namespace App\Services\Overtime;

use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\OvertimeRates;
use App\Models\Bms\OvertimeRequest;
use App\Models\Bms\OvertimeRequestDay;
use App\Models\Bms\OvertimeRequestHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OvertimeRequestService
{
    private OvertimeCalculationService $overtimeCalculation;
    private OvertimeDayLockService $overtimeDayLockService;

    public function __construct(
        OvertimeCalculationService $overtimeCalculation,
        OvertimeDayLockService $overtimeDayLockService
    ) {
        $this->overtimeCalculation = $overtimeCalculation;
        $this->overtimeDayLockService = $overtimeDayLockService;
    }

    public function getPfNumberFromUserId(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        try {
            $user = AuthUser::find($userId);
            if (!$user || empty($user->pf_number)) {
                return null;
            }

            return $user->pf_number;
        } catch (\Exception $e) {
            Log::warning('Failed to get PF number from user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function getDailyRate(?string $pfNumber): float
    {
        if (!$pfNumber) {
            return 0;
        }

        try {
            $employee = BridgeEmployee::byPfno($pfNumber)->first();

            if (!$employee || !$employee->educational_level_id) {
                return 0;
            }

            $overtimeRate = OvertimeRates::where('educational_levels_id', $employee->educational_level_id)
                ->where('is_active', true)
                ->first();

            return $overtimeRate ? (float) $overtimeRate->rate : 0;
        } catch (\Exception $e) {
            Log::warning('Failed to get daily rate for employee', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function getEmployeeSalary(?string $pfNumber): float
    {
        if (!$pfNumber) {
            return 0;
        }

        try {
            $employee = BridgeEmployee::byPfno($pfNumber)->first();

            if (!$employee) {
                Log::warning('Employee not found when fetching salary for overtime', [
                    'pf_number' => $pfNumber,
                ]);

                return 0;
            }

            return (float) ($employee->basicsalary ?? 0);
        } catch (\Exception $e) {
            Log::error('Failed to get employee salary for overtime', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function calculateGrossPayFromNet(float $netPay): float
    {
        if ($netPay <= 0) {
            return 0;
        }

        return round($netPay * (100 / 70), 2);
    }

    public function calculateWithholdingTax(float $grossPay, float $netPay): float
    {
        if ($grossPay <= 0 || $netPay < 0) {
            return 0;
        }

        return round($grossPay - $netPay, 2);
    }

    public function getAttendanceData(?string $pfNumber, string $date): ?array
    {
        try {
            if (!$pfNumber) {
                Log::warning('PF number not provided', ['date' => $date]);

                return null;
            }

            return $this->overtimeCalculation->getOvertimeInfo($pfNumber, $date);
        } catch (\Exception $e) {
            Log::warning('Failed to get attendance data', [
                'pf_number' => $pfNumber,
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a new overtime request, or resubmit when `id` points to a Returned request owned by the applicant.
     *
     * @return array{success: true, overtimeRequest: OvertimeRequest, responseData: array, notification: array, isResubmit: bool}
     *         |array{success: false, error: string, errors?: array, http: int}
     */
    public function createRequest(array $input, int $employeeId): array
    {
        $validator = Validator::make($input, [
            'id' => 'nullable|integer',
            'month' => 'required|date_format:Y-m',
            'days' => 'required|array|min:1',
            'days.*.dayDate' => 'required|date',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
                'http' => 422,
            ];
        }

        $pfNumber = $this->getPfNumberFromUserId($employeeId);

        if (!$pfNumber) {
            return [
                'success' => false,
                'error' => 'PF number not found for user. Please ensure your account is linked to an employee record.',
                'http' => 400,
            ];
        }

        $month = $input['month'];
        $days = $input['days'];

        $returnedRequest = $this->getReturnedOvertimeRequest($input, $pfNumber, $employeeId, $month);
        if (is_array($returnedRequest)) {
            return $returnedRequest;
        }

        /** @var OvertimeRequest|null $returnedRequest */
        $excludeRequestId = $returnedRequest?->id;

        $processedResult = $this->validateAndProcessDays($pfNumber, $month, $days, $excludeRequestId);
        if (!$processedResult['success']) {
            return $processedResult;
        }

        $processedDays = $processedResult['processedDays'];
        $totalOvertimeHours = $processedResult['totalOvertimeHours'];
        $totalDays = count($processedDays);

        $amountsResult = $this->calculateRequestAmounts($pfNumber, $totalDays);
        if (!$amountsResult['success']) {
            return $amountsResult;
        }

        $amounts = $amountsResult['amounts'];
        $isResubmit = $returnedRequest !== null;

        try {
            DB::beginTransaction();

            if ($isResubmit) {
                $overtimeRequest = $this->persistReturnedRequestUpdate(
                    $returnedRequest,
                    $processedDays,
                    $amounts,
                    $totalOvertimeHours,
                    $totalDays,
                    $pfNumber,
                    $employeeId
                );
            } else {
                $overtimeRequest = $this->persistNewRequest(
                    $pfNumber,
                    $month,
                    $processedDays,
                    $amounts,
                    $totalOvertimeHours,
                    $totalDays,
                    $employeeId
                );
            }

            DB::commit();

            $monthDisplay = Carbon::parse($month)->format('F Y');

            return [
                'success' => true,
                'isResubmit' => $isResubmit,
                'overtimeRequest' => $overtimeRequest,
                'responseData' => $this->buildResponseData($overtimeRequest),
                'notification' => [
                    'monthDisplay' => $monthDisplay,
                    'totalOvertimeHours' => $totalOvertimeHours,
                    'totalDays' => $totalDays,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($isResubmit ? 'Failed to resubmit overtime request' : 'Failed to create overtime request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $input,
            ]);

            return [
                'success' => false,
                'error' => ($isResubmit ? 'Failed to resubmit overtime request: ' : 'Failed to create overtime request: ') . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array, message: string}
     *         |array{success: false, error: string, errors?: array, http: int}
     */
    public function validateOvertimeAmount(int $employeeId, int $estimatedDays): array
    {
        $validator = Validator::make(
            ['estimated_days' => $estimatedDays],
            ['estimated_days' => 'required|integer|min:1']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
                'http' => 422,
            ];
        }

        try {
            $pfNumber = $this->getPfNumberFromUserId($employeeId);
            if (!$pfNumber) {
                return [
                    'success' => false,
                    'error' => 'PF number not found for user. Please ensure your account is linked to an employee record.',
                    'http' => 400,
                ];
            }

            $dailyRate = $this->getDailyRate($pfNumber);
            $estimatedNetPay = round($dailyRate * $estimatedDays, 2);
            $salary = $this->getEmployeeSalary($pfNumber);

            if ($salary <= 0) {
                return [
                    'success' => false,
                    'error' => 'Employee salary not found or invalid. Please contact HR to update your salary information.',
                    'http' => 400,
                ];
            }

            $halfSalary = round($salary / 2, 2);
            $estimatedGrossPay = $this->calculateGrossPayFromNet($estimatedNetPay);
            $estimatedTax = $this->calculateWithholdingTax($estimatedGrossPay, $estimatedNetPay);
            $isValid = $estimatedGrossPay <= $halfSalary;

            $message = $isValid
                ? 'Overtime amount is within allowed limit.'
                : 'Overtime amount exceeds maximum allowed. Please reduce the number of overtime days.';

            return [
                'success' => true,
                'data' => [
                    'isValid' => $isValid,
                    'salary' => $salary,
                    'halfSalary' => $halfSalary,
                    'estimatedNetPay' => $estimatedNetPay,
                    'estimatedGrossPay' => $estimatedGrossPay,
                    'estimatedTax' => $estimatedTax,
                    'dailyRate' => $dailyRate,
                    'estimatedDays' => $estimatedDays,
                    'message' => $message,
                ],
                'message' => $isValid ? 'Validation passed' : 'Validation failed',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to validate overtime amount', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'employee_id' => $employeeId,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to validate overtime amount',
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array, message: string}
     *         |array{success: false, error: string, errors?: array, http: int}
     */
    public function checkOvertimeForDate(int $employeeId, string $date): array
    {
        $validator = Validator::make(
            ['date' => $date],
            ['date' => 'required|date']
        );

        if ($validator->fails()) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
                'http' => 422,
            ];
        }

        try {
            $pfNumber = $this->getPfNumberFromUserId($employeeId);
            if (!$pfNumber) {
                return [
                    'success' => false,
                    'error' => 'PF number not found for user. Please ensure your account is linked to an employee record.',
                    'http' => 400,
                ];
            }

            $overtimeInfo = $this->overtimeCalculation->getOvertimeInfo($pfNumber, $date);

            if (!$overtimeInfo) {
                return [
                    'success' => false,
                    'error' => "No attendance data found for date: {$date}. Please ensure you have signed in on this day.",
                    'http' => 404,
                ];
            }

            return [
                'success' => true,
                'data' => $overtimeInfo,
                'message' => 'Overtime information retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Error checking overtime for date', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to check overtime information',
                'http' => 500,
            ];
        }
    }

    /**
     * Resubmit only when the client sends the returned request id (no auto-pick by month).
     *
     * @return OvertimeRequest|null|array{success: false, error: string, http: int}
     */
    private function getReturnedOvertimeRequest(array $input, string $pfNumber, int $employeeId, string $month)
    {
        if (empty($input['id'])) {
            return null;
        }

        $request = OvertimeRequest::find((int) $input['id']);

        if (!$request) {
            return [
                'success' => false,
                'error' => 'Overtime request not found',
                'http' => 404,
            ];
        }

        if ($request->status !== 'Returned') {
            return [
                'success' => false,
                'error' => 'Only returned overtime requests can be resubmitted. Provide the id of a returned request.',
                'http' => 422,
            ];
        }

        if ((int) $request->created_by !== $employeeId) {
            return [
                'success' => false,
                'error' => 'You do not have permission to resubmit this overtime request',
                'http' => 403,
            ];
        }

        if ($request->pf_number !== $pfNumber) {
            return [
                'success' => false,
                'error' => 'Overtime request does not belong to your employee record',
                'http' => 403,
            ];
        }

        $requestMonth = $request->month ? $request->month->format('Y-m') : null;
        if ($requestMonth !== $month) {
            return [
                'success' => false,
                'error' => 'Month does not match the returned overtime request',
                'http' => 422,
            ];
        }

        return $request;
    }

    /**
     * @return array{success: true, processedDays: array, totalOvertimeHours: float}
     *         |array{success: false, error: string, http: int}
     */
    private function validateAndProcessDays(
        string $pfNumber,
        string $month,
        array $days,
        ?int $excludeRequestId = null
    ): array {
        $processedDays = [];
        $totalOvertimeHours = 0;

        foreach ($days as $day) {
            $dayDate = $day['dayDate'];
            $dayMonth = Carbon::parse($dayDate)->format('Y-m');

            if ($dayMonth !== $month) {
                return [
                    'success' => false,
                    'error' => 'All days must be in the same month',
                    'http' => 400,
                ];
            }

            if ($this->overtimeDayLockService->isDayBlocked($pfNumber, $dayDate, $excludeRequestId)) {
                return [
                    'success' => false,
                    'error' => "Date {$dayDate} has already been submitted in another active overtime request.",
                    'http' => 400,
                ];
            }

            $attendanceData = $this->getAttendanceData($pfNumber, $dayDate);

            if (!$attendanceData) {
                return [
                    'success' => false,
                    'error' => "No attendance data found for date: {$dayDate}. Please ensure you have signed in on this day.",
                    'http' => 400,
                ];
            }

            if (!$attendanceData['canApply'] || $attendanceData['overtimeHours'] <= 0) {
                return [
                    'success' => false,
                    'error' => "No overtime hours found for date: {$dayDate}. Overtime hours must be greater than 0.",
                    'http' => 400,
                ];
            }

            $processedDays[] = [
                'dayDate' => $dayDate,
                'overtimeHours' => $attendanceData['overtimeHours'],
                'overtimeType' => $attendanceData['overtimeType'],
                'isHoliday' => $attendanceData['isHoliday'],
                'specialTaskId' => $attendanceData['specialTask']['id'] ?? null,
                'reason' => $attendanceData['reason'],
            ];

            $totalOvertimeHours += $attendanceData['overtimeHours'];
        }

        return [
            'success' => true,
            'processedDays' => $processedDays,
            'totalOvertimeHours' => $totalOvertimeHours,
        ];
    }

    /**
     * @return array{success: true, amounts: array}
     *         |array{success: false, error: string, errors?: array, http: int}
     */
    private function calculateRequestAmounts(string $pfNumber, int $totalDays): array
    {
        $dailyRate = $this->getDailyRate($pfNumber);
        $netPay = round($dailyRate * $totalDays, 2);
        $salary = $this->getEmployeeSalary($pfNumber);

        if ($salary <= 0) {
            return [
                'success' => false,
                'error' => 'Employee salary not found or invalid. Please contact HR to update your salary information.',
                'http' => 400,
            ];
        }

        $halfSalary = round($salary / 2, 2);
        $grossPay = $this->calculateGrossPayFromNet($netPay);
        $grossRequired = $halfSalary;

        if ($grossPay > $halfSalary) {
            return [
                'success' => false,
                'error' => 'Overtime gross pay exceeds half of your salary. Please reduce the number of overtime days.',
                'errors' => [
                    'gross_pay' => $grossPay,
                    'half_salary' => $halfSalary,
                    'salary' => $salary,
                    'max_allowed' => $halfSalary,
                    'net_pay' => $netPay,
                    'daily_rate' => $dailyRate,
                    'total_days' => $totalDays,
                ],
                'http' => 400,
            ];
        }

        $tax = $this->calculateWithholdingTax($grossPay, $netPay);

        return [
            'success' => true,
            'amounts' => [
                'dailyRate' => $dailyRate,
                'netPay' => $netPay,
                'salary' => $salary,
                'halfSalary' => $halfSalary,
                'grossPay' => $grossPay,
                'grossRequired' => $grossRequired,
                'tax' => $tax,
            ],
        ];
    }

    private function syncRequestDays(OvertimeRequest $request, array $processedDays, float $dailyRate): void
    {
        OvertimeRequestDay::where('overtime_request_id', $request->id)->delete();

        foreach ($processedDays as $day) {
            OvertimeRequestDay::create([
                'overtime_request_id' => $request->id,
                'day_date' => $day['dayDate'],
                'overtime_hours' => $day['overtimeHours'],
                'daily_rate' => $dailyRate,
                'amount' => $dailyRate,
                'overtime_type' => $day['overtimeType'],
                'is_holiday' => $day['isHoliday'],
                'special_task_id' => $day['specialTaskId'],
                'overtime_reason' => $day['reason'],
            ]);
        }
    }

    private function persistNewRequest(
        string $pfNumber,
        string $month,
        array $processedDays,
        array $amounts,
        float $totalOvertimeHours,
        int $totalDays,
        int $employeeId
    ): OvertimeRequest {
        $workflowPending = $this->mapStatusToWorkflow('Pending');

        $overtimeRequest = OvertimeRequest::create([
            'pf_number' => $pfNumber,
            'month' => $month,
            'total_overtime_hours' => $totalOvertimeHours,
            'total_days' => $totalDays,
            'total_amount' => $amounts['netPay'],
            'tax' => $amounts['tax'],
            'gross_pay' => $amounts['grossPay'],
            'gross_required' => $amounts['grossRequired'],
            'salary' => $amounts['salary'],
            'half_salary' => $amounts['halfSalary'],
            'net_pay' => $amounts['netPay'],
            'overtime_daily_rate' => $amounts['dailyRate'],
            'status' => 'Pending',
            'workflow_status' => $workflowPending,
            'created_by' => $employeeId,
            'updated_by' => $employeeId,
        ]);

        $this->syncRequestDays($overtimeRequest, $processedDays, $amounts['dailyRate']);

        $employeeRole = $this->resolveEmployeeRoleFromPfNumber($pfNumber);

        OvertimeRequestHistory::create([
            'overtime_request_id' => $overtimeRequest->id,
            'action' => 'Apply Overtime',
            'status' => 'Pending',
            'workflow_status' => $workflowPending,
            'performed_by' => $pfNumber,
            'performed_by_role' => $employeeRole,
            'comment' => 'Overtime request submitted for ' . Carbon::parse($month)->format('F Y'),
        ]);

        return $overtimeRequest;
    }

    private function persistReturnedRequestUpdate(
        OvertimeRequest $overtimeRequest,
        array $processedDays,
        array $amounts,
        float $totalOvertimeHours,
        int $totalDays,
        string $pfNumber,
        int $employeeId
    ): OvertimeRequest {
        $workflowPending = $this->mapStatusToWorkflow('Pending');

        $overtimeRequest->total_overtime_hours = $totalOvertimeHours;
        $overtimeRequest->total_days = $totalDays;
        $overtimeRequest->total_amount = $amounts['netPay'];
        $overtimeRequest->tax = $amounts['tax'];
        $overtimeRequest->gross_pay = $amounts['grossPay'];
        $overtimeRequest->gross_required = $amounts['grossRequired'];
        $overtimeRequest->salary = $amounts['salary'];
        $overtimeRequest->half_salary = $amounts['halfSalary'];
        $overtimeRequest->net_pay = $amounts['netPay'];
        $overtimeRequest->overtime_daily_rate = $amounts['dailyRate'];
        $overtimeRequest->status = 'Pending';
        $overtimeRequest->workflow_status = $workflowPending;
        $overtimeRequest->updated_by = $employeeId;
        $overtimeRequest->save();

        $this->syncRequestDays($overtimeRequest, $processedDays, $amounts['dailyRate']);

        $employeeRole = $this->resolveEmployeeRoleFromPfNumber($pfNumber);
        $monthDisplay = $overtimeRequest->month->format('F Y');

        OvertimeRequestHistory::create([
            'overtime_request_id' => $overtimeRequest->id,
            'action' => 'Resubmit Overtime',
            'status' => 'Pending',
            'workflow_status' => $workflowPending,
            'performed_by' => $pfNumber,
            'performed_by_role' => $employeeRole,
            'comment' => 'Overtime request resubmitted after correction for ' . $monthDisplay,
        ]);

        return $overtimeRequest->fresh();
    }

    private function buildResponseData(OvertimeRequest $overtimeRequest): array
    {
        return [
            'id' => $overtimeRequest->id,
            'pfNumber' => $overtimeRequest->pf_number,
            'month' => $overtimeRequest->month->format('Y-m'),
            'totalOvertimeHours' => (float) $overtimeRequest->total_overtime_hours,
            'totalDays' => $overtimeRequest->total_days,
            'salary' => (float) $overtimeRequest->salary,
            'halfSalary' => (float) $overtimeRequest->half_salary,
            'grossPay' => (float) $overtimeRequest->gross_pay,
            'grossRequired' => (float) $overtimeRequest->gross_required,
            'tax' => (float) $overtimeRequest->tax,
            'netPay' => (float) $overtimeRequest->net_pay,
            'overtimeDailyRate' => (float) $overtimeRequest->overtime_daily_rate,
            'status' => $overtimeRequest->status,
            'workflowStatus' => $this->mapStatusToWorkflow($overtimeRequest->status),
            'createdAt' => $overtimeRequest->created_at,
        ];
    }

    private function mapStatusToWorkflow(?string $status): string
    {
        if (!$status) {
            return 'Applied';
        }

        $statusMap = [
            'Pending' => 'Applied',
            'Validator Approved' => 'Validated',
            'Reviewer Approved' => 'Reviewed',
            'Returned' => 'Returned',
            'In Batch' => 'Reviewed',
            'Submitted to Payment' => 'Reviewed',
            'Payment Processing' => 'Reviewed',
            'Payment Approved' => 'Approved',
            'Payment Completed' => 'Paid',
            'Rejected' => 'Rejected',
            'Payment Rejected' => 'Rejected',
        ];

        return $statusMap[$status] ?? 'Applied';
    }

    private function resolveEmployeeRoleFromPfNumber(string $pfNumber): string
    {
        try {
            if ($pfNumber === '') {
                return 'Employee';
            }

            $employee = BridgeEmployee::byPfno($pfNumber)->first();

            if (!$employee || !$employee->national_id) {
                Log::warning('Employee not found for PF number when getting role', [
                    'pf_number' => $pfNumber,
                ]);

                return 'Employee';
            }

            $bridgeEmployeeRole = BridgeEmployeeRole::where('national_id', $employee->national_id)
                ->where('is_active', true)
                ->with('role')
                ->first();

            return $bridgeEmployeeRole && $bridgeEmployeeRole->role
                ? ($bridgeEmployeeRole->role->role_name ?? 'Employee')
                : 'Employee';
        } catch (\Exception $e) {
            Log::warning('Failed to get employee role from PF number', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage(),
            ]);

            return 'Employee';
        }
    }
}
