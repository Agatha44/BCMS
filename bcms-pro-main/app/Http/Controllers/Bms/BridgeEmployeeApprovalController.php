<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Services\ReferralApprovalService;
use App\Constants\EmployeeStatus;
use App\Models\Bms\BridgeEmployeeStatus;
use App\Models\Bms\ReferralRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BridgeEmployeeApprovalController extends BasicController
{
    protected $approvalService;

    public function __construct(ReferralApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Get all pending approvals
     * This method now returns data from both referral_request and bridge_employee_status for backward compatibility
     */
    public function pendingApprovals(Request $request)
    {
        try {
            $type = $request->input('type'); // pending_creation, pending_termination, pending_deletion, pending_update

            // Map type to action type for referral requests
            $actionTypeMap = [
                'pending_creation' => ReferralRequest::ACTION_CREATE,
                'pending_update' => ReferralRequest::ACTION_UPDATE,
                'pending_deletion' => ReferralRequest::ACTION_DELETE,
                'pending_termination' => ReferralRequest::ACTION_TERMINATE,
                'PENDING_APPROVAL' => ReferralRequest::ACTION_CREATE,
            ];

            // Get pending approvals from bridge_employee_status for backward compatibility
            $query = BridgeEmployeeStatus::pendingApproval()->with('employee');

            if ($type) {
                $statusMap = [
                    'pending_creation' => EmployeeStatus::PENDING_CREATION,
                    'pending_update' => EmployeeStatus::PENDING_UPDATE,
                    'pending_deletion' => EmployeeStatus::PENDING_DELETION,
                    'pending_termination' => EmployeeStatus::PENDING_TERMINATION,
                    'PENDING_APPROVAL' => EmployeeStatus::PENDING_APPROVAL,
                ];

                if (isset($statusMap[$type])) {
                    $query->where('employee_status', $statusMap[$type]);
                }
            }

            $pendingApprovals = $query->latest()->get();

            return $this->sendResponse([
                'pending_approvals' => $pendingApprovals,
                'count' => $pendingApprovals->count(),
            ], 'Pending approvals retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching pending approvals: ' . $e->getMessage());
            return $this->sendError('Failed to retrieve pending approvals: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve employee creation
     *
     * NOTE: This endpoint now accepts both ReferralRequest ID and BridgeEmployeeStatus ID.
     *       The system now uses referral requests for approval. If a referral request ID
     *       is provided, it will be used directly. Otherwise, it will try to find the
     *       corresponding referral request from the status ID.
     */
    public function approveCreation(Request $request, $Id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // First, try to resolve as referral request ID
            $referralRequest = $this->resolveReferralRequest($Id, ReferralRequest::ACTION_CREATE);
            
            if ($referralRequest) {
                // Use referral request approval
                $this->approvalService->approveReferralRequest(
                    $referralRequest->id,
                    $request->input('comments')
                );
            } else {
                // Fallback: try to resolve as status ID (for backward compatibility)
                $actualStatusId = $this->resolveStatusId($Id, EmployeeStatus::PENDING_CREATION);
                
                if (!$actualStatusId) {
                    return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
                }
                
                $this->approvalService->approveByStatusId(
                    $actualStatusId,
                    $request->input('comments')
                );
            }

            return $this->sendResponse(null, 'Employee creation approved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error approving employee creation: Record not found', [
                'id' => $Id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error approving employee creation: ' . $e->getMessage(), [
                'id' => $Id
            ]);
            return $this->sendError('Failed to approve employee creation: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve employee termination
     *
     * NOTE: This endpoint now accepts both ReferralRequest ID and BridgeEmployeeStatus ID.
     */
    public function approveTermination(Request $request, $Id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // First, try to resolve as referral request ID
            $referralRequest = $this->resolveReferralRequest($Id, ReferralRequest::ACTION_TERMINATE);
            
            if ($referralRequest) {
                // Use referral request approval
                $this->approvalService->approveReferralRequest(
                    $referralRequest->id,
                    $request->input('comments')
                );
            } else {
                // Fallback: try to resolve as status ID (for backward compatibility)
                $actualStatusId = $this->resolveStatusId($Id, EmployeeStatus::PENDING_TERMINATION);
                
                if (!$actualStatusId) {
                    return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
                }
                
                $this->approvalService->approveByStatusId($actualStatusId, $request->input('comments'));
            }

            return $this->sendResponse(null, 'Employee termination approved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error approving employee termination: Record not found', [
                'id' => $Id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error approving employee termination: ' . $e->getMessage(), [
                'id' => $Id
            ]);
            return $this->sendError('Failed to approve employee termination: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve employee update
     *
     * NOTE: This endpoint now accepts both ReferralRequest ID and BridgeEmployeeStatus ID.
     */
    public function approveUpdate(Request $request, $statusId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // First, try to resolve as referral request ID
            $referralRequest = $this->resolveReferralRequest($statusId, ReferralRequest::ACTION_UPDATE);
            
            if ($referralRequest) {
                // Use referral request approval
                $this->approvalService->approveReferralRequest(
                    $referralRequest->id,
                    $request->input('comments')
                );
            } else {
                // Fallback: try to resolve as status ID (for backward compatibility)
                $actualStatusId = $this->resolveStatusId($statusId, EmployeeStatus::PENDING_UPDATE);
                
                if (!$actualStatusId) {
                    return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
                }
                
                $this->approvalService->approveByStatusId(
                    $actualStatusId,
                    $request->input('comments')
                );
            }

            return $this->sendResponse(null, 'Employee update approved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error approving employee update: Record not found', [
                'id' => $statusId,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error approving employee update: ' . $e->getMessage(), [
                'id' => $statusId
            ]);
            return $this->sendError('Failed to approve employee update: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve employee deletion
     *
     * NOTE: This endpoint now accepts both ReferralRequest ID and BridgeEmployeeStatus ID.
     */
    public function approveDeletion(Request $request, $Id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'comments' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // First, try to resolve as referral request ID
            $referralRequest = $this->resolveReferralRequest($Id, ReferralRequest::ACTION_DELETE);
            
            if ($referralRequest) {
                // Use referral request approval
                $this->approvalService->approveReferralRequest(
                    $referralRequest->id,
                    $request->input('comments')
                );
            } else {
                // Fallback: try to resolve as status ID (for backward compatibility)
                $actualStatusId = $this->resolveStatusId($Id, EmployeeStatus::PENDING_DELETION);
                
                if (!$actualStatusId) {
                    return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
                }
                
                $this->approvalService->approveByStatusId($actualStatusId, $request->input('comments'));
            }

            return $this->sendResponse(null, 'Employee deletion approved successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error approving employee deletion: Record not found', [
                'id' => $Id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error approving employee deletion: ' . $e->getMessage(), [
                'id' => $Id
            ]);
            return $this->sendError('Failed to approve employee deletion: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Reject pending request
     *
     * NOTE: This endpoint now accepts both ReferralRequest ID and BridgeEmployeeStatus ID.
     */
    public function reject(Request $request, $statusId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // First, try to resolve as referral request ID (try all action types)
            $referralRequest = $this->resolveReferralRequest($statusId, null);
            
            if ($referralRequest) {
                // Use referral request rejection
                $this->approvalService->rejectReferralRequest(
                    $referralRequest->id,
                    $request->input('rejection_reason')
                );
            } else {
                // Fallback: try to resolve as status ID (for backward compatibility)
                $actualStatusId = $this->resolveStatusId($statusId, null);
                
                if (!$actualStatusId) {
                    return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
                }
                
                $this->approvalService->rejectByStatusId($actualStatusId, $request->input('rejection_reason'));
            }

            return $this->sendResponse(null, 'Request rejected successfully');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Error rejecting request: Record not found', [
                'id' => $statusId,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Referral request or status record not found. Please ensure the ID is valid.', [], 0, 404);
        } catch (\Exception $e) {
            Log::error('Error rejecting request: ' . $e->getMessage(), [
                'id' => $statusId
            ]);
            return $this->sendError('Failed to reject request: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Resolve referral request from identifier
     *
     * @param string|int $identifier The referral request ID or status ID
     * @param string|null $expectedActionType The expected action type (optional)
     * @return ReferralRequest|null The resolved referral request or null if not found
     */
    private function resolveReferralRequest($identifier, ?string $expectedActionType = null): ?ReferralRequest
    {
        // Convert to string for safe comparison
        $identifierStr = (string) $identifier;

        // First, try to find directly as referral request ID
        if (is_numeric($identifierStr)) {
            $referralRequest = ReferralRequest::find($identifierStr);
            
            if ($referralRequest) {
                // If expected action type is provided, verify it matches
                if ($expectedActionType !== null && $referralRequest->action_type !== $expectedActionType) {
                    return null;
                }
                
                // Only return if it's pending
                if ($referralRequest->isPending()) {
                    return $referralRequest;
                }
            }
        }

        // If not found as referral request ID, try to find via status ID
        $statusId = $this->resolveStatusId($identifier, null);
        if ($statusId) {
            return $this->approvalService->findReferralRequestByStatusId($statusId);
        }

        return null;
    }

    /**
     * Resolve status ID from a BridgeEmployeeStatus ID only.
     *
     * This method previously accepted both status IDs and national IDs, but
     * has been simplified to avoid relying on long national IDs in URLs.
     *
     * @param string|int $identifier The status ID
     * @param string|null $expectedStatus The expected employee status (optional)
     * @return int|null The resolved status ID or null if not found/mismatched
     */
    private function resolveStatusId($identifier, ?string $expectedStatus = null): ?int
    {
        // Convert to string for safe comparison
        $identifierStr = (string) $identifier;

        // Check if it's a valid integer that won't overflow
        // PHP_INT_MAX is 9223372036854775807 (19 digits) on 64-bit systems
        $maxIntStr = (string) PHP_INT_MAX;
        $isValidInt = is_numeric($identifierStr)
            && $identifierStr > 0
            && (strlen($identifierStr) < strlen($maxIntStr) ||
                (strlen($identifierStr) === strlen($maxIntStr) && $identifierStr <= $maxIntStr));

        if (!$isValidInt) {
            // Only numeric, in-range IDs are accepted now
            return null;
        }

        $intValue = (int) $identifierStr;

        // Verify the cast didn't overflow (cast back to string should match)
        if ((string) $intValue !== $identifierStr) {
            return null;
        }

        // Try to find by status ID
        $status = BridgeEmployeeStatus::find($intValue);
        if (!$status) {
            return null;
        }

        // If expected status is provided, verify it matches
        if ($expectedStatus !== null && $status->employee_status !== $expectedStatus) {
            return null;
        }

        return $intValue;
    }
}

