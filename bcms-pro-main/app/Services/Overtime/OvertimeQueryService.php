<?php

namespace App\Services\Overtime;

use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\OvertimeRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OvertimeQueryService
{
    private OvertimeRequestService $overtimeRequestService;

    public function __construct(
        OvertimeRequestService $overtimeRequestService
    ) {
        $this->overtimeRequestService = $overtimeRequestService;
    }

    public function getEmployeeByPfNumber(?string $pfNumber): ?BridgeEmployee
    {
        if (!$pfNumber) {
            return null;
        }

        return BridgeEmployee::byPfno($pfNumber)->first();
    }

    /**
     * @param BridgeEmployee|object|null $employee
     */
    public function formatEmployeeName($employee): ?string
    {
        if (!$employee) {
            return null;
        }

        if ($employee instanceof BridgeEmployee && isset($employee->full_name)) {
            return $employee->full_name;
        }

        return trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? ''));
    }

    /**
     * @param Collection<int, OvertimeRequest>|iterable $overtimeRequests
     */
    public function employeesKeyedByOvertimeRequests($overtimeRequests): Collection
    {
        $pfNumbers = collect($overtimeRequests)->pluck('pf_number')->filter()->unique()->values()->all();

        return $this->loadEmployeesByPfNumbers($pfNumbers);
    }

    /**
     * @param array{
     *     stage?: string|null,
     *     status?: string|null,
     *     month?: string|null,
     *     search?: string|null,
     *     pf_number?: string|null,
     *     employee_id?: mixed,
     *     per_page?: int|string|null
     * } $filters
     * @param array{
     *     auth_user_id: int,
     *     is_supervisor: bool,
     *     is_validator: bool,
     *     is_reviewer: bool,
     *     current_pf?: string|null
     * } $context
     * @return array{success: true, data: array, pagination: array}|array{success: false, error: string, http: int}
     */
    public function paginateOvertimeRequests(array $filters, array $context): array
    {
        $authUserId = (int) $context['auth_user_id'];
        $isSupervisor = (bool) $context['is_supervisor'];
        $isValidator = (bool) $context['is_validator'];
        $isReviewer = (bool) $context['is_reviewer'];
        $currentUserPfNumber = $context['current_pf'] ?? null;
        $stage = $filters['stage'] ?? null;

        $query = OvertimeRequest::select(
            'overtime_requests.id',
            'overtime_requests.pf_number',
            'overtime_requests.month',
            'overtime_requests.total_overtime_hours',
            'overtime_requests.total_days',
            'overtime_requests.total_amount',
            'overtime_requests.status',
            'overtime_requests.workflow_status',
            'overtime_requests.created_at',
            'bridge_employee.fname as first_name',
            'bridge_employee.mname as middle_name',
            'bridge_employee.sname as surname'
        )
            ->leftJoin('bridge_employee', function ($join) {
                $join->on(DB::raw('overtime_requests.pf_number'), '=', DB::raw('bridge_employee.pfno'));
            });

        if ($stage) {
            switch ($stage) {
                case 'validator_pending':
                    if (!$isValidator) {
                        return [
                            'success' => false,
                            'error' => 'You do not have permission to view validator pending records.',
                            'http' => 403,
                        ];
                    }
                    $query->where('overtime_requests.status', 'Pending')
                        ->where('overtime_requests.created_by', '!=', $authUserId);
                    break;

                case 'reviewer_pending':
                    if (!$isReviewer) {
                        return [
                            'success' => false,
                            'error' => 'You do not have permission to view reviewer pending records.',
                            'http' => 403,
                        ];
                    }
                    $query->where('overtime_requests.status', 'Validator Approved')
                        ->where('overtime_requests.created_by', '!=', $authUserId);
                    break;

                case 'approved':
                    $query->whereIn('overtime_requests.status', [
                        'Reviewer Approved', 'In Batch', 'Submitted to Payment',
                        'Payment Approved', 'Payment Processing', 'Payment Completed',
                    ]);
                    break;

                case 'rejected':
                    $query->where('overtime_requests.status', 'Rejected');
                    break;

                case 'returned':
                    $query->where('overtime_requests.status', 'Returned');
                    if (!$isSupervisor && $currentUserPfNumber) {
                        $query->where('overtime_requests.pf_number', $currentUserPfNumber);
                    }
                    break;
            }
        }

        $isViewingStageList = in_array($stage, ['validator_pending', 'reviewer_pending']);
        $canViewAllRequests = $isSupervisor || $isValidator || $isReviewer || $isViewingStageList;

        if (!$canViewAllRequests && $currentUserPfNumber) {
            $query->where('overtime_requests.pf_number', $currentUserPfNumber);
        }

        if ($isSupervisor && !empty($filters['pf_number'])) {
            $query->where('overtime_requests.pf_number', $filters['pf_number']);
        }

        if ($isSupervisor && isset($filters['employee_id']) && $filters['employee_id'] !== '' && $filters['employee_id'] !== null) {
            $employeePfNumber = $this->overtimeRequestService->getPfNumberFromUserId((int) $filters['employee_id']);
            if ($employeePfNumber) {
                $query->where('overtime_requests.pf_number', $employeePfNumber);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if (!$stage && !empty($filters['status'])) {
            $query->where('overtime_requests.status', $filters['status']);
        }

        if (!empty($filters['month'])) {
            $query->where('overtime_requests.month', $filters['month'] . '-01');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bridge_employee.fname', 'like', "%{$search}%")
                    ->orWhere('bridge_employee.mname', 'like', "%{$search}%")
                    ->orWhere('bridge_employee.sname', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderBy('overtime_requests.created_at', 'desc')->paginate($perPage);

        $data = collect($paginator->items())->map(function ($row) {
            $employeeName = trim(($row->first_name ?? '') . ' ' . ($row->middle_name ?? '') . ' ' . ($row->surname ?? ''));
            $monthDate = \Carbon\Carbon::parse($row->month);
            $workflowStatus = $this->mapToWorkflowStatus($row->status);

            return [
                'id' => $row->id,
                'pfNumber' => $row->pf_number,
                'employeeName' => $employeeName,
                'month' => $monthDate->format('Y-m'),
                'monthDisplay' => $monthDate->format('F Y'),
                'totalOvertimeHours' => (float) $row->total_overtime_hours,
                'totalDays' => $row->total_days,
                'totalAmount' => (float) $row->total_amount,
                'status' => $row->status,
                'workflowStatus' => $workflowStatus,
                'createdAt' => $row->created_at,
            ];
        });

        return [
            'success' => true,
            'data' => $data->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @param array{
     *     status?: string|null,
     *     month?: string|null,
     *     search?: string|null,
     *     per_page?: int|string|null
     * } $filters
     * @return array{success: true, data: array, pagination: array}|array{success: false, error: string, http: int}
     */
    public function paginateMyActions(int $authUserId, ?string $currentUserPfNumber, array $filters): array
    {
        $query = OvertimeRequest::select(
            'overtime_requests.id',
            'overtime_requests.pf_number',
            'overtime_requests.month',
            'overtime_requests.total_overtime_hours',
            'overtime_requests.total_amount',
            'overtime_requests.workflow_status',
            'overtime_requests.total_days',
            'overtime_requests.status',
            'overtime_requests.created_at',
            'bridge_employee.fname as first_name',
            'bridge_employee.mname as middle_name',
            'bridge_employee.sname as surname'
        )
            ->leftJoin('bridge_employee', function ($join) {
                $join->on(DB::raw('overtime_requests.pf_number'), '=', DB::raw('bridge_employee.pfno'));
            })
            ->where(function ($q) use ($authUserId, $currentUserPfNumber) {
                $q->where('overtime_requests.validator_approved_by', $authUserId)
                    ->orWhere('overtime_requests.reviewer_approved_by', $authUserId);

                if ($currentUserPfNumber) {
                    $q->orWhereIn('overtime_requests.id', function ($subQuery) use ($currentUserPfNumber) {
                        $subQuery->select('overtime_request_id')
                            ->from('overtime_request_history')
                            ->where('performed_by', $currentUserPfNumber);
                    });
                }
            });

        if (!empty($filters['status'])) {
            $query->where('overtime_requests.status', $filters['status']);
        }

        if (!empty($filters['month'])) {
            $monthStart = $filters['month'] . '-01';
            $query->where('overtime_requests.month', $monthStart);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('bridge_employee.fname', 'like', "%{$search}%")
                    ->orWhere('bridge_employee.mname', 'like', "%{$search}%")
                    ->orWhere('bridge_employee.sname', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        if ($perPage < 1) {
            $perPage = 10;
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderBy('overtime_requests.created_at', 'desc')->paginate($perPage);

        $data = collect($paginator->items())->map(function ($row) {
            $employeeName = trim(($row->first_name ?? '') . ' ' . ($row->middle_name ?? '') . ' ' . ($row->surname ?? ''));
            $monthDate = \Carbon\Carbon::parse($row->month);

            return [
                'id' => $row->id,
                'pfNumber' => $row->pf_number,
                'employeeName' => $employeeName,
                'month' => $monthDate->format('Y-m'),
                'monthDisplay' => $monthDate->format('F Y'),
                'totalOvertimeHours' => (float) $row->total_overtime_hours,
                'totalDays' => $row->total_days,
                'totalAmount' => (float) $row->total_amount,
                'workflowStatus' => $row->workflow_status,
                'status' => $row->status,
                'createdAt' => $row->created_at,
            ];
        });

        return [
            'success' => true,
            'data' => $data->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @return array{success: true, data: array}|array{success: false, error: string, http: int}
     */
    public function getOvertimeRequestDetail(int $id, ?int $authUserId = null): array
    {
        try {
            $overtimeRequest = OvertimeRequest::with(['days', 'history', 'batch'])->find($id);

            if (!$overtimeRequest) {
                return [
                    'success' => false,
                    'error' => 'Overtime request not found',
                    'http' => 404,
                ];
            }

            $employee = $this->getEmployeeByPfNumber($overtimeRequest->pf_number);
            $users = $this->loadApprovalUsers($overtimeRequest);
            $employees = $this->loadHistoryEmployees($overtimeRequest->history);

            $overtimeRequest->setRelation('validatorApprovedBy', $users->get($overtimeRequest->validator_approved_by));
            $overtimeRequest->setRelation('reviewerApprovedBy', $users->get($overtimeRequest->reviewer_approved_by));

            $data = $this->buildOvertimeRequestData($overtimeRequest, $employee, $employees, $authUserId);

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve overtime request', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to retrieve overtime request: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    public function mapToWorkflowStatus(?string $status): string
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

    private function loadApprovalUsers(OvertimeRequest $overtimeRequest): Collection
    {
        $userIds = array_filter([
            $overtimeRequest->validator_approved_by,
            $overtimeRequest->reviewer_approved_by,
        ]);

        if (empty($userIds)) {
            return collect();
        }

        try {
            return AuthUser::on('bcmis')
                ->whereIn('id', $userIds)
                ->select('id', 'first_name', 'middle_name', 'surname')
                ->get()
                ->keyBy('id');
        } catch (\Exception $e) {
            Log::warning('Failed to load approval users from bcmis connection, using fallback', [
                'user_ids' => $userIds,
                'error' => $e->getMessage(),
            ]);

            return DB::table('auth_user')
                ->whereIn('id', $userIds)
                ->select('id', 'first_name', 'middle_name', 'surname')
                ->get()
                ->mapWithKeys(function ($row) {
                    $user = new AuthUser();
                    $user->id = $row->id;
                    $user->first_name = $row->first_name;
                    $user->middle_name = $row->middle_name;
                    $user->surname = $row->surname;

                    return [$row->id => $user];
                });
        }
    }

    /**
     * @param list<string> $pfNumbers
     */
    private function loadEmployeesByPfNumbers(array $pfNumbers): Collection
    {
        if (empty($pfNumbers)) {
            return collect();
        }

        return BridgeEmployee::where(function ($query) use ($pfNumbers) {
            $query->whereIn('pfno', $pfNumbers)->orWhereIn('pfno2', $pfNumbers);
        })
            ->select('pfno', 'pfno2', 'fname', 'mname', 'sname', 'national_id')
            ->get()
            ->mapWithKeys(function ($emp) use ($pfNumbers) {
                $pfNo = $emp->pfno ?? $emp->pfno2;

                return in_array($pfNo, $pfNumbers) ? [$pfNo => $emp] : [];
            })
            ->filter();
    }

    private function loadHistoryEmployees($history): Collection
    {
        $pfNumbers = $history->pluck('performed_by')->filter()->unique()->toArray();

        return $this->loadEmployeesByPfNumbers($pfNumbers);
    }

    public function formatUserName($user): ?string
    {
        if (!$user) {
            return null;
        }

        return trim(($user->first_name ?? '') . ' ' . ($user->middle_name ?? '') . ' ' . ($user->surname ?? ''));
    }

    private function buildOvertimeRequestData(
        OvertimeRequest $overtimeRequest,
        $employee,
        Collection $employees,
        ?int $authUserId = null
    ): array {
        $workflowStatus = $this->mapToWorkflowStatus($overtimeRequest->status);
        $canResubmit = $overtimeRequest->status === 'Returned'
            && $authUserId !== null
            && (int) $overtimeRequest->created_by === $authUserId;

        return [
            'id' => $overtimeRequest->id,
            'pfNumber' => $overtimeRequest->pf_number,
            'employeeName' => $this->formatEmployeeName($employee),
            'month' => $overtimeRequest->month->format('Y-m'),
            'monthDisplay' => $overtimeRequest->month_display,
            'totalOvertimeHours' => (float) $overtimeRequest->total_overtime_hours,
            'totalDays' => $overtimeRequest->total_days,
            'status' => $overtimeRequest->status,
            'workflowStatus' => $workflowStatus,
            'notes' => $overtimeRequest->notes,
            'createdAt' => $overtimeRequest->created_at,
            'updatedAt' => $overtimeRequest->updated_at,
            'latestReturn' => $this->latestReturnFromHistory($overtimeRequest, $employees),
            'canEdit' => $canResubmit,
            'canResubmit' => $canResubmit,
            'approvalStages' => [
                'validator' => [
                    'approvedBy' => $this->formatUserName($overtimeRequest->validatorApprovedBy),
                    'approvedAt' => $overtimeRequest->validator_approved_at,
                    'comment' => $overtimeRequest->validator_comment,
                ],
                'reviewer' => [
                    'approvedBy' => $this->formatUserName($overtimeRequest->reviewerApprovedBy),
                    'approvedAt' => $overtimeRequest->reviewer_approved_at,
                    'comment' => $overtimeRequest->reviewer_comment,
                ],
            ],
            'batch' => $overtimeRequest->batch ? [
                'id' => $overtimeRequest->batch->id,
                'batch_number' => $overtimeRequest->batch->batch_number,
                'batch_name' => $overtimeRequest->batch->batch_name,
                'status' => $overtimeRequest->batch->status,
            ] : null,
            'externalStatus' => $overtimeRequest->external_status,
            'externalPaymentRequestId' => $overtimeRequest->external_payment_request_id,
            'externalStatusUpdatedAt' => $overtimeRequest->external_status_updated_at,
            'days' => $overtimeRequest->days->map(function ($day) use ($overtimeRequest) {
                $attendanceData = $this->overtimeRequestService->getAttendanceData(
                    $overtimeRequest->pf_number,
                    $day->day_date->format('Y-m-d')
                );

                return [
                    'id' => $day->id,
                    'dayDate' => $day->day_date->format('Y-m-d'),
                    'daily_rate' => $day->daily_rate,
                    'timeIn' => $attendanceData['timeIn'] ?? null,
                    'timeOut' => $attendanceData['timeOut'] ?? null,
                    'overtimeHours' => (float) $day->overtime_hours,
                    'amount' => (float) $day->amount,
                    'overtimeType' => $day->overtime_type ?? 'regular',
                    'isHoliday' => (bool) ($day->is_holiday ?? false),
                    'specialTaskId' => $day->special_task_id,
                    'overtimeReason' => $day->overtime_reason,
                ];
            }),
            'history' => $overtimeRequest->history
                ->filter(function ($history) {
                    $allowedActions = [
                        'Apply Overtime',
                        'Validate Overtime',
                        'Review Overtime',
                        'Returned to Applicant',
                        'Resubmit Overtime',
                        'Approve Payment (EOffice)',
                        'Paid',
                        'Rejected',
                        'Payment Rejected',
                    ];
                    $action = $history->action ?? '';
                    $actionLower = strtolower($action);
                    $allowedActionsLower = array_map('strtolower', $allowedActions);

                    return in_array($actionLower, $allowedActionsLower);
                })
                ->map(function ($history) use ($employees) {
                    $histEmployee = $employees->get($history->performed_by);
                    $histWorkflow = $this->mapToWorkflowStatus($history->status);

                    return [
                        'id' => $history->id,
                        'action' => $history->action,
                        'status' => $history->status,
                        'workflowStatus' => $histWorkflow,
                        'performedBy' => $histEmployee ? $this->formatEmployeeName($histEmployee) : ($history->performed_by ?? 'System'),
                        'performedByRole' => $history->performed_by_role,
                        'comment' => $history->comment,
                        'timestamp' => $history->created_at,
                    ];
                }),
        ];
    }

    private function latestReturnFromHistory(OvertimeRequest $overtimeRequest, Collection $employees): ?array
    {
        $entry = $overtimeRequest->history
            ->where('action', 'Returned to Applicant')
            ->sortByDesc('created_at')
            ->first();

        if (!$entry) {
            return null;
        }

        $performer = $employees->get($entry->performed_by);

        return [
            'returnedBy' => $performer ? $this->formatEmployeeName($performer) : ($entry->performed_by),
            'returnedByRole' => $entry->performed_by_role,
            'returnedAt' => $entry->created_at,
            'comment' => $entry->comment,
        ];
    }
}
