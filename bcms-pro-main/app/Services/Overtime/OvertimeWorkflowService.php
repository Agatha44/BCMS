<?php

namespace App\Services\Overtime;

use App\Models\AuthUser;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\Bms\OvertimeRequest;
use App\Models\Bms\OvertimeRequestHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OvertimeWorkflowService
{
    private OvertimeRequestService $overtimeRequestService;
    private OvertimeQueryService $overtimeQueryService;
    private OvertimeNotificationService $overtimeNotificationService;

    public function __construct(
        OvertimeRequestService $overtimeRequestService,
        OvertimeQueryService $overtimeQueryService,
        OvertimeNotificationService $overtimeNotificationService
    ) {
        $this->overtimeRequestService = $overtimeRequestService;
        $this->overtimeQueryService = $overtimeQueryService;
        $this->overtimeNotificationService = $overtimeNotificationService;
    }

    public function isSupervisor($userId = null): bool
    {
        $currentUserId = $userId ?? auth()->id();
        if (!$currentUserId) {
            return false;
        }

        $supervisorRoles = ['overtime validator', 'overtime reviewer'];

        try {
            $pfNumber = $this->overtimeRequestService->getPfNumberFromUserId($currentUserId);
            if (!$pfNumber) {
                return false;
            }

            $employee = BridgeEmployee::byPfno($pfNumber)->first();
            if (!$employee || !$employee->national_id) {
                return false;
            }

            $bridgeEmployeeRoles = BridgeEmployeeRole::where('national_id', $employee->national_id)
                ->active()
                ->with('role')
                ->get();

            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                if ($bridgeEmployeeRole->role) {
                    $roleName = strtolower($bridgeEmployeeRole->role->role_name ?? '');
                    if (in_array($roleName, $supervisorRoles, true)) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check supervisor role', [
                'user_id' => $currentUserId,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function isOvertimeValidator($userId = null): bool
    {
        return $this->hasOvertimeRole('overtime validator', $userId);
    }

    public function isOvertimeReviewer($userId = null): bool
    {
        return $this->hasOvertimeRole('overtime reviewer', $userId);
    }

    public function hasOvertimeRole(string $roleName, $userId = null): bool
    {
        $currentUserId = $userId ?? auth()->id();
        if (!$currentUserId) {
            return false;
        }

        try {
            $pfNumber = $this->overtimeRequestService->getPfNumberFromUserId($currentUserId);
            if (!$pfNumber) {
                return false;
            }

            $employee = BridgeEmployee::byPfno($pfNumber)->first();
            if (!$employee || !$employee->national_id) {
                return false;
            }

            $bridgeEmployeeRoles = BridgeEmployeeRole::where('national_id', $employee->national_id)
                ->active()
                ->with('role')
                ->get();

            $targetRoleName = strtolower($roleName);
            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                if ($bridgeEmployeeRole->role) {
                    $currentRoleName = strtolower($bridgeEmployeeRole->role->role_name ?? '');
                    if ($currentRoleName === $targetRoleName) {
                        return true;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning("Failed to check {$roleName} role", [
                'user_id' => $currentUserId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getEmployeeRoleFromPfNumber($pfNumber): string
    {
        try {
            if (!$pfNumber) {
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

    public function getOvertimeValidators(): array
    {
        return $this->getUsersByRole('overtime validator');
    }

    public function getOvertimeReviewers(): array
    {
        return $this->getUsersByRole('overtime reviewer');
    }

    /**
     * @return array{success: true, data: array, message: string}|array{success: false, error: string, errors?: array, http: int}
     */
    public function updateStatus(int $id, array $input, ?int $userId): array
    {
        try {
            $validator = Validator::make($input, [
                'status' => 'required|in:Pending,Validator Approved,Reviewer Approved,In Batch,Submitted to Payment,Payment Approved,Payment Rejected,Payment Processing,Payment Completed,Rejected',
                'action' => 'required|string|max:50',
                'comment' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'http' => 422,
                ];
            }

            $overtimeRequest = OvertimeRequest::find($id);

            if (!$overtimeRequest) {
                return [
                    'success' => false,
                    'error' => 'Overtime request not found',
                    'http' => 404,
                ];
            }

            $newStatus = $input['status'];
            $comment = $input['comment'] ?? '';

            $pfNumber = $this->overtimeRequestService->getPfNumberFromUserId($userId);
            if (!$pfNumber) {
                return [
                    'success' => false,
                    'error' => 'PF number not found for user. Please ensure your account is linked to an employee record.',
                    'http' => 400,
                ];
            }

            $employeeRole = $this->getEmployeeRoleFromPfNumber($pfNumber);

            if ($newStatus === 'Validator Approved' && !$this->isOvertimeValidator($userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to approve as overtime validator. Only users with "overtime validator" role can perform this action.',
                    'http' => 403,
                ];
            }

            if ($newStatus === 'Reviewer Approved' && !$this->isOvertimeReviewer($userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to approve as overtime reviewer. Only users with "overtime reviewer" role can perform this action.',
                    'http' => 403,
                ];
            }

            if (($newStatus === 'Validator Approved' || $newStatus === 'Reviewer Approved') && $overtimeRequest->created_by == $userId) {
                return [
                    'success' => false,
                    'error' => 'You cannot approve your own request',
                    'http' => 403,
                ];
            }

            if ($newStatus === 'Rejected' && $overtimeRequest->created_by == $userId) {
                $isValidator = $this->isOvertimeValidator($userId);
                $isReviewer = $this->isOvertimeReviewer($userId);
                if ($isValidator || $isReviewer) {
                    return [
                        'success' => false,
                        'error' => 'You cannot reject your own request',
                        'http' => 403,
                    ];
                }
            }

            $workflowError = $this->validateWorkflowProgression($overtimeRequest, $newStatus);
            if ($workflowError) {
                return [
                    'success' => false,
                    'error' => $workflowError,
                    'http' => 422,
                ];
            }

            DB::beginTransaction();

            switch ($newStatus) {
                case 'Validator Approved':
                    $overtimeRequest->validator_approved_by = $userId;
                    $overtimeRequest->validator_approved_at = now();
                    $overtimeRequest->validator_comment = $comment;
                    break;

                case 'Reviewer Approved':
                    $overtimeRequest->reviewer_approved_by = $userId;
                    $overtimeRequest->reviewer_approved_at = now();
                    $overtimeRequest->reviewer_comment = $comment;
                    break;

                case 'Rejected':
                    if (empty($overtimeRequest->notes)) {
                        $overtimeRequest->notes = $comment;
                    } else {
                        $overtimeRequest->notes .= "\n\nRejection: " . $comment;
                    }
                    break;
            }

            $workflowStatus = $this->overtimeQueryService->mapToWorkflowStatus($newStatus);
            $overtimeRequest->status = $newStatus;
            $overtimeRequest->workflow_status = $workflowStatus;
            $overtimeRequest->updated_by = $userId;
            $overtimeRequest->save();

            $historyAction = $input['action'];
            if ($newStatus === 'Validator Approved') {
                $historyAction = 'Validate Overtime';
            } elseif ($newStatus === 'Reviewer Approved') {
                $historyAction = 'Review Overtime';
            }

            OvertimeRequestHistory::create([
                'overtime_request_id' => $overtimeRequest->id,
                'action' => $historyAction,
                'status' => $newStatus,
                'workflow_status' => $workflowStatus,
                'performed_by' => $pfNumber,
                'performed_by_role' => $employeeRole,
                'comment' => $comment,
            ]);

            DB::commit();

            $this->overtimeNotificationService->sendWorkflowNotifications(
                $overtimeRequest,
                $newStatus,
                $comment,
                $newStatus === 'Validator Approved' ? $this->getOvertimeReviewers() : []
            );

            return [
                'success' => true,
                'data' => [
                    'id' => $overtimeRequest->id,
                    'status' => $overtimeRequest->status,
                    'workflowStatus' => $workflowStatus,
                    'updatedAt' => $overtimeRequest->updated_at,
                ],
                'message' => $this->successMessageForStatus($newStatus),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update overtime request status', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update overtime request status: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    /**
     * @return array{success: true, data: array, message: string}|array{success: false, error: string, errors?: array, http: int}
     */
    public function returnOvertimeRequest(int $id, array $input, ?int $userId): array
    {
        try {
            $validator = Validator::make($input, [
                'comment' => 'required|string|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'http' => 422,
                ];
            }

            $overtimeRequest = OvertimeRequest::find($id);

            if (!$overtimeRequest) {
                return [
                    'success' => false,
                    'error' => 'Overtime request not found',
                    'http' => 404,
                ];
            }

            $currentStatus = $overtimeRequest->status;

            if (!in_array($currentStatus, ['Pending', 'Validator Approved'], true)) {
                return [
                    'success' => false,
                    'error' => "Cannot return request with status: {$currentStatus}",
                    'http' => 422,
                ];
            }

            $pfNumber = $this->overtimeRequestService->getPfNumberFromUserId($userId);
            if (!$pfNumber) {
                return [
                    'success' => false,
                    'error' => 'PF number not found for user. Please ensure your account is linked to an employee record.',
                    'http' => 400,
                ];
            }

            if ($overtimeRequest->created_by == $userId) {
                return [
                    'success' => false,
                    'error' => 'You cannot return your own request',
                    'http' => 403,
                ];
            }

            if ($currentStatus === 'Pending' && !$this->isOvertimeValidator($userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to return this request. Only users with "overtime validator" role can return pending requests.',
                    'http' => 403,
                ];
            }

            if ($currentStatus === 'Validator Approved' && !$this->isOvertimeReviewer($userId)) {
                return [
                    'success' => false,
                    'error' => 'You do not have permission to return this request. Only users with "overtime reviewer" role can return validated requests.',
                    'http' => 403,
                ];
            }

            $workflowError = $this->validateWorkflowProgression($overtimeRequest, 'Returned');
            if ($workflowError) {
                return [
                    'success' => false,
                    'error' => $workflowError,
                    'http' => 422,
                ];
            }

            $comment = trim($input['comment']);
            $employeeRole = $this->getEmployeeRoleFromPfNumber($pfNumber);
            $workflowStatus = $this->overtimeQueryService->mapToWorkflowStatus('Returned');

            DB::beginTransaction();

            if ($currentStatus === 'Validator Approved') {
                $overtimeRequest->validator_approved_by = null;
                $overtimeRequest->validator_approved_at = null;
                $overtimeRequest->validator_comment = null;
            }

            if (empty($overtimeRequest->notes)) {
                $overtimeRequest->notes = 'Returned: ' . $comment;
            } else {
                $overtimeRequest->notes .= "\n\nReturned: " . $comment;
            }

            $overtimeRequest->status = 'Returned';
            $overtimeRequest->workflow_status = $workflowStatus;
            $overtimeRequest->updated_by = $userId;
            $overtimeRequest->save();

            OvertimeRequestHistory::create([
                'overtime_request_id' => $overtimeRequest->id,
                'action' => 'Returned to Applicant',
                'status' => 'Returned',
                'workflow_status' => $workflowStatus,
                'performed_by' => $pfNumber,
                'performed_by_role' => $employeeRole,
                'comment' => $comment,
            ]);

            DB::commit();

            $this->overtimeNotificationService->sendWorkflowNotifications(
                $overtimeRequest,
                'Returned',
                $comment,
                []
            );

            return [
                'success' => true,
                'data' => [
                    'id' => $overtimeRequest->id,
                    'status' => $overtimeRequest->status,
                    'workflowStatus' => $workflowStatus,
                    'updatedAt' => $overtimeRequest->updated_at,
                ],
                'message' => 'Overtime request has been returned to the applicant for correction',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to return overtime request to applicant', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to return overtime request to applicant: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    private function validateWorkflowProgression(OvertimeRequest $overtimeRequest, string $newStatus): ?string
    {
        $currentStatus = $overtimeRequest->status;

        $validTransitions = [
            'Pending' => ['Validator Approved', 'Returned', 'Rejected'],
            'Validator Approved' => ['Reviewer Approved', 'Returned', 'Rejected'],
            'Reviewer Approved' => ['In Batch', 'Rejected'],
            'In Batch' => ['Submitted to Payment', 'Rejected'],
            'Submitted to Payment' => ['Payment Approved', 'Payment Rejected', 'Payment Processing'],
            'Payment Processing' => ['Payment Approved', 'Payment Rejected', 'Payment Completed'],
            'Payment Approved' => ['Payment Completed'],
            'Payment Completed' => [],
            'Payment Rejected' => [],
            'Rejected' => [],
            'Returned' => [],
        ];

        if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
            return "Invalid workflow transition. Current status: {$currentStatus}, cannot transition to: {$newStatus}";
        }

        return null;
    }

    private function getUsersByRole(string $roleName): array
    {
        try {
            $role = DB::connection('bcmis2')->table('roles')
                ->whereRaw('LOWER(role_name) = ?', [strtolower($roleName)])
                ->first();

            if (!$role) {
                return [];
            }

            $userIds = [];
            $bridgeEmployeeRoles = BridgeEmployeeRole::where('role_id', $role->id)
                ->active()
                ->get();

            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                $user = AuthUser::where('nida', $bridgeEmployeeRole->national_id)->first();
                if ($user) {
                    $userIds[] = $user->id;
                }
            }

            return array_unique($userIds);
        } catch (\Exception $e) {
            Log::warning("Failed to get users with role: {$roleName}", [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function successMessageForStatus(string $newStatus): string
    {
        if ($newStatus === 'Validator Approved') {
            return 'Overtime request has been validated successfully';
        }

        if ($newStatus === 'Reviewer Approved') {
            return 'Overtime request has been reviewed successfully';
        }

        return 'Overtime request status has been updated successfully';
    }
}
