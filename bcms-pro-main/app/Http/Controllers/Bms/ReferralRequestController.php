<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\ReferralRequest;
use App\Services\ReferralApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReferralRequestController extends BasicController
{
    protected $approvalService;

    public function __construct(ReferralApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Get all pending referral requests filtered by module
     *
     * Query Parameters:
     * - module_code (required): Filter by module code (e.g., EMPLOYMENT_MANAGEMENT)
     * - action_type (optional): Filter by action type (CREATE, UPDATE, DELETE, TERMINATE)
     * - national_id (optional): Filter by employee national ID
     */
    public function pending(Request $request)
    {
        try {
            $moduleCode = $request->input('module_code');
            $actionType = $request->input('action_type');
            $nationalId = $request->input('national_id');

            // Validate module_code is provided
            if (!$moduleCode) {
                return $this->sendError('Module code is required. Please provide a module_code parameter.', [], 0, 400);
            }

            $query = ReferralRequest::pending()
                ->with(['initiator', 'approver']);

            // Filter by module (required)
            $query->byModule($moduleCode);

            // Optional filters
            if ($actionType) {
                $query->byActionType($actionType);
            }

            if ($nationalId) {
                $query->byNationalId($nationalId);
            }

            $pendingRequests = $query->orderBy('created_at', 'desc')->get();

            // Enhance response with module-specific data
            $enhancedRequests = $pendingRequests->map(function ($request) use ($moduleCode) {
                $data = [
                    'id' => $request->id,
                    'module_code' => $request->module_code,
                    'action_type' => $request->action_type,
                    'national_id' => $request->national_id,
                    'reference_id' => $request->reference_id,
                    'status' => $request->status,
                    'remarks' => $request->remarks,
                    'created_at' => $request->created_at,
                    'actioned_at' => $request->actioned_at,
                    'initiator' => $request->initiator ? [
                        'id' => $request->initiator->id,
                        'username' => $request->initiator->username ?? null,
                        'name' => ($request->initiator->first_name ?? '') . ' ' . ($request->initiator->surname ?? ''),
                    ] : null,
                    'approver' => $request->approver ? [
                        'id' => $request->approver->id,
                        'username' => $request->approver->username ?? null,
                        'name' => ($request->approver->first_name ?? '') . ' ' . ($request->approver->surname ?? ''),
                    ] : null,
                ];

                // Add module-specific data
                if ($moduleCode === ReferralRequest::MODULE_EMPLOYMENT_MANAGEMENT && $request->national_id) {
                    try {
                        $employee = \App\Models\Bms\BridgeEmployee::find($request->national_id);
                        if ($employee) {
                            $data['employee'] = [
                                'national_id' => $employee->national_id,
                                'fname' => $employee->fname,
                                'sname' => $employee->sname,
                                'email' => $employee->email,
                                'username' => $employee->username,
                                'mobile' => $employee->mobile,
                                'employee_status' => $employee->employee_status,
                                'pfno' => $employee->pfno,
                                'account_status' => $employee->account_status,
                            ];

                            // Get the latest employee status record for this request
                            $expectedStatuses = $this->mapActionTypeToStatuses($request->action_type);
                            $employeeStatus = \App\Models\Bms\BridgeEmployeeStatus::byNationalId($request->national_id)
                                ->whereIn('employee_status', $expectedStatuses)
                                ->orderBy('statusdate', 'desc')
                                ->orderBy('id', 'desc')
                                ->first();

                            if ($employeeStatus) {
                                $data['employee_status'] = [
                                    'id' => $employeeStatus->id,
                                    'employee_status' => $employeeStatus->employee_status,
                                    'description' => $employeeStatus->description,
                                    'statusdate' => $employeeStatus->statusdate,
                                    'cdate' => $employeeStatus->cdate,
                                ];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to load employee data for referral request', [
                            'referral_request_id' => $request->id,
                            'national_id' => $request->national_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return $data;
            });

            return $this->sendResponse([
                'pending_requests' => $enhancedRequests,
                'count' => $enhancedRequests->count(),
                'module_code' => $moduleCode,
                'filters' => [
                    'action_type' => $actionType,
                    'national_id' => $nationalId,
                ],
            ], 'Pending referral requests retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching pending referral requests: ' . $e->getMessage(), [
                'module_code' => $request->input('module_code'),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve pending referral requests: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Map action type to employee statuses (returns array to handle multiple possible statuses)
     */
    private function mapActionTypeToStatuses(string $actionType): array
    {
        $statusMap = [
            ReferralRequest::ACTION_CREATE => [
                \App\Constants\EmployeeStatus::PENDING_APPROVAL,
                \App\Constants\EmployeeStatus::PENDING_CREATION,
            ],
            ReferralRequest::ACTION_UPDATE => [
                \App\Constants\EmployeeStatus::PENDING_UPDATE,
            ],
            ReferralRequest::ACTION_DELETE => [
                \App\Constants\EmployeeStatus::PENDING_DELETION,
            ],
            ReferralRequest::ACTION_TERMINATE => [
                \App\Constants\EmployeeStatus::PENDING_TERMINATION,
            ],
        ];

        return $statusMap[$actionType] ?? [\App\Constants\EmployeeStatus::PENDING_APPROVAL];
    }

    /**
     * Get all referral requests (pending, approved, rejected)
     */
    public function index(Request $request)
    {
        try {
            $moduleCode = $request->input('module_code');
            $actionType = $request->input('action_type');
            $status = $request->input('status');
            $nationalId = $request->input('national_id');

            $query = ReferralRequest::with(['initiator', 'approver']);

            if ($moduleCode) {
                $query->byModule($moduleCode);
            }

            if ($actionType) {
                $query->byActionType($actionType);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($nationalId) {
                $query->byNationalId($nationalId);
            }

            $requests = $query->orderBy('created_at', 'desc')->get();

            // Transform requests to replace initiated_by and approved_by with concatenated names
            $transformedRequests = $requests->map(function ($request) {
                $requestData = $request->toArray();
                
                // Replace initiated_by with concatenated name
                if ($request->initiator) {
                    $requestData['initiated_by'] = trim(($request->initiator->first_name ?? '') . ' ' . ($request->initiator->surname ?? ''));
                } else {
                    $requestData['initiated_by'] = null;
                }
                
                // Replace approved_by with concatenated name
                if ($request->approver) {
                    $requestData['approved_by'] = trim(($request->approver->first_name ?? '') . ' ' . ($request->approver->surname ?? ''));
                } else {
                    $requestData['approved_by'] = null;
                }
                
                return $requestData;
            });

            return $this->sendResponse([
                'referral_requests' => $transformedRequests,
                'count' => $transformedRequests->count(),
            ], 'Referral requests retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching referral requests: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve referral requests: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get a specific referral request by ID
     */
    public function show($id)
    {
        try {
            $referralRequest = ReferralRequest::with(['initiator', 'approver'])->findOrFail($id);

            // Transform request to replace initiated_by and approved_by with concatenated names
            $requestData = $referralRequest->toArray();
            
            // Replace initiated_by with concatenated name
            if ($referralRequest->initiator) {
                $requestData['initiated_by'] = trim(($referralRequest->initiator->first_name ?? '') . ' ' . ($referralRequest->initiator->surname ?? ''));
            } else {
                $requestData['initiated_by'] = null;
            }
            
            // Replace approved_by with concatenated name
            if ($referralRequest->approver) {
                $requestData['approved_by'] = trim(($referralRequest->approver->first_name ?? '') . ' ' . ($referralRequest->approver->surname ?? ''));
            } else {
                $requestData['approved_by'] = null;
            }

            return $this->sendResponse($requestData, 'Referral request retrieved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Referral request not found', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error fetching referral request: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve referral request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve a referral request
     */
    public function approve(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'remarks' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $this->approvalService->approveReferralRequest(
                $id,
                $request->input('remarks')
            );

            return $this->sendResponse(null, 'Referral request approved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error approving referral request: Request not found', [
                'referral_request_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request not found', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error approving referral request: ' . $e->getMessage(), [
                'referral_request_id' => $id
            ]);
            return $this->sendError('Failed to approve referral request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Reject a referral request
     */
    public function reject(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'remarks' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $this->approvalService->rejectReferralRequest(
                $id,
                $request->input('remarks')
            );

            return $this->sendResponse(null, 'Referral request rejected successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error rejecting referral request: Request not found', [
                'referral_request_id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request not found', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error rejecting referral request: ' . $e->getMessage(), [
                'referral_request_id' => $id
            ]);
            return $this->sendError('Failed to reject referral request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get statistics for referral requests
     */
    public function statistics(Request $request)
    {
        try {
            $moduleCode = $request->input('module_code');

            $query = ReferralRequest::query();

            if ($moduleCode) {
                $query->byModule($moduleCode);
            }

            $statistics = [
                'total' => (clone $query)->count(),
                'pending' => (clone $query)->pending()->count(),
                'approved' => (clone $query)->approved()->count(),
                'rejected' => (clone $query)->rejected()->count(),
                'by_action_type' => [
                    'CREATE' => (clone $query)->byActionType(ReferralRequest::ACTION_CREATE)->count(),
                    'UPDATE' => (clone $query)->byActionType(ReferralRequest::ACTION_UPDATE)->count(),
                    'DELETE' => (clone $query)->byActionType(ReferralRequest::ACTION_DELETE)->count(),
                    'TERMINATE' => (clone $query)->byActionType(ReferralRequest::ACTION_TERMINATE)->count(),
                ],
            ];

            return $this->sendResponse($statistics, 'Referral request statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching referral request statistics: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve statistics: ' . $e->getMessage(), [], 0, 500);
        }
    }
}
