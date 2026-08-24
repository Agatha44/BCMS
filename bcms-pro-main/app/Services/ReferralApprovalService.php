<?php

namespace App\Services;

use App\Constants\EmployeeStatus;
use App\Models\Bms\ReferralRequest;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeStatus;
use App\Models\Bms\BridgeOffice;
use App\Models\AuthUser;
use App\Services\Payroll\MandatoryDeductionProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ReferralApprovalService
{
    const MODULE_EMPLOYMENT_MANAGEMENT = 'EMPLOYMENT_MANAGEMENT';

    /**
     * Create a referral request for employee action
     */
    public function createReferralRequest(
        string $moduleCode,
        string $actionType,
        string $nationalId,
        ?string $referenceId = null,
        ?string $remarks = null
    ): ReferralRequest {
        DB::beginTransaction();
        try {
            $referralRequest = ReferralRequest::create([
                'module_code' => $moduleCode,
                'action_type' => $actionType,
                'national_id' => $nationalId,
                'reference_id' => $referenceId,
                'status' => ReferralRequest::STATUS_PENDING,
                'initiated_by' => auth()->id(),
                'remarks' => $remarks,
            ]);

            DB::commit();

            Log::info('Referral request created', [
                'referral_request_id' => $referralRequest->id,
                'module_code' => $moduleCode,
                'action_type' => $actionType,
                'national_id' => $nationalId,
            ]);

            return $referralRequest;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create referral request', [
                'module_code' => $moduleCode,
                'action_type' => $actionType,
                'national_id' => $nationalId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit employee creation for approval
     */
    public function submitEmployeeCreation(BridgeEmployee $employee, ?string $remarks = null): array
    {
        DB::beginTransaction();
        try {
            // Create referral request
            $referralRequest = $this->createReferralRequest(
                self::MODULE_EMPLOYMENT_MANAGEMENT,
                ReferralRequest::ACTION_CREATE,
                $employee->national_id,
                null,
                $remarks
            );

            // Check if employee_status record exists
            $employeeStatus = BridgeEmployeeStatus::byNationalId($employee->national_id)
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // If no record exists, create one with PENDING_APPROVAL status
            if (!$employeeStatus) {
                $employeeStatus = BridgeEmployeeStatus::create([
                    'national_id' => $employee->national_id,
                    'employee_status' => EmployeeStatus::PENDING_APPROVAL,
                    'description' => 'Employee creation submitted for approval',
                    'cby' => auth()->id(),
                    'cdate' => now(),
                    'statusdate' => now(),
                    'verified' => false,
                    'approved' => false,
                ]);

                // Update employee status
                $employee->update([
                    'employee_status' => EmployeeStatus::PENDING_APPROVAL,
                ]);
            } else {
                // Update existing status to PENDING_APPROVAL
                $employeeStatus->update([
                    'employee_status' => EmployeeStatus::PENDING_APPROVAL,
                    'previous_employee_status' => $employeeStatus->employee_status,
                    'statusdate' => now(),
                    'verified' => false,
                    'approved' => false,
                ]);

                $employee->update([
                    'employee_status' => EmployeeStatus::PENDING_APPROVAL,
                ]);
            }

            DB::commit();

            Log::info('Employee creation submitted for approval', [
                'national_id' => $employee->national_id,
                'referral_request_id' => $referralRequest->id,
                'employee_status_id' => $employeeStatus->id,
            ]);

            return [
                'referral_request' => $referralRequest,
                'employee_status' => $employeeStatus,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit employee creation for approval', [
                'national_id' => $employee->national_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit employee update for approval
     */
    public function submitEmployeeUpdate(string $nationalId, array $proposedChanges, ?string $remarks = null): array
    {
        DB::beginTransaction();
        try {
            $employee = BridgeEmployee::findOrFail($nationalId);

            // Create referral request
            $referralRequest = $this->createReferralRequest(
                self::MODULE_EMPLOYMENT_MANAGEMENT,
                ReferralRequest::ACTION_UPDATE,
                $nationalId,
                null,
                $remarks
            );

            // Get current status
            $employeeStatus = BridgeEmployeeStatus::byNationalId($nationalId)
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // If no status record exists, create one using employee's current status
            if (!$employeeStatus) {
                $currentEmployeeStatus = $employee->employee_status ?: EmployeeStatus::ACTIVE;
                
                $employeeStatus = BridgeEmployeeStatus::create([
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                    'description' => 'Initial employee status record created',
                    'cby' => auth()->id(),
                    'cdate' => now(),
                    'statusdate' => now(),
                    'verified' => false,
                    'approved' => false,
                ]);

                Log::info('Created initial employee status record for update request', [
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                ]);
            }

            // Create status record with proposed changes
            $newStatus = BridgeEmployeeStatus::create([
                'national_id' => $nationalId,
                'employee_status' => EmployeeStatus::PENDING_UPDATE,
                'previous_employee_status' => $employeeStatus->employee_status,
                'proposed_changes' => $proposedChanges,
                'description' => 'Employee information update submitted for approval',
                'cby' => auth()->id(),
                'cdate' => now(),
                'statusdate' => now(),
                'verified' => false,
                'approved' => false,
            ]);

            // Note: Employee status remains unchanged until approval

            DB::commit();

            Log::info('Employee update submitted for approval', [
                'national_id' => $nationalId,
                'referral_request_id' => $referralRequest->id,
                'employee_status_id' => $newStatus->id,
            ]);

            return [
                'referral_request' => $referralRequest,
                'employee_status' => $newStatus,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit employee update for approval', [
                'national_id' => $nationalId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit employee deletion for approval
     */
    public function submitEmployeeDeletion(string $nationalId, ?string $remarks = null): array
    {
        DB::beginTransaction();
        try {
            $employee = BridgeEmployee::findOrFail($nationalId);

            // Create referral request
            $referralRequest = $this->createReferralRequest(
                self::MODULE_EMPLOYMENT_MANAGEMENT,
                ReferralRequest::ACTION_DELETE,
                $nationalId,
                null,
                $remarks
            );

            // Get current status
            $employeeStatus = BridgeEmployeeStatus::byNationalId($nationalId)
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // If no status record exists, create one using employee's current status
            if (!$employeeStatus) {
                $currentEmployeeStatus = $employee->employee_status ?: EmployeeStatus::ACTIVE;
                
                $employeeStatus = BridgeEmployeeStatus::create([
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                    'description' => 'Initial employee status record created',
                    'cby' => auth()->id(),
                    'cdate' => now(),
                    'statusdate' => now(),
                    'verified' => false,
                    'approved' => false,
                ]);

                Log::info('Created initial employee status record for deletion request', [
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                ]);
            }

            // Create status record for deletion request
            $newStatus = BridgeEmployeeStatus::create([
                'national_id' => $nationalId,
                'employee_status' => EmployeeStatus::PENDING_DELETION,
                'previous_employee_status' => $employeeStatus->employee_status,
                'description' => 'Employee deletion submitted for approval',
                'cby' => auth()->id(),
                'cdate' => now(),
                'statusdate' => now(),
                'verified' => false,
                'approved' => false,
            ]);

            // Update employee status
            $employee->update([
                'employee_status' => EmployeeStatus::PENDING_DELETION,
            ]);

            DB::commit();

            Log::info('Employee deletion submitted for approval', [
                'national_id' => $nationalId,
                'referral_request_id' => $referralRequest->id,
                'employee_status_id' => $newStatus->id,
            ]);

            return [
                'referral_request' => $referralRequest,
                'employee_status' => $newStatus,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit employee deletion for approval', [
                'national_id' => $nationalId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Submit employee termination for approval
     */
    public function submitEmployeeTermination(string $nationalId, ?string $remarks = null): array
    {
        DB::beginTransaction();
        try {
            $employee = BridgeEmployee::findOrFail($nationalId);

            // Create referral request
            $referralRequest = $this->createReferralRequest(
                self::MODULE_EMPLOYMENT_MANAGEMENT,
                ReferralRequest::ACTION_TERMINATE,
                $nationalId,
                null,
                $remarks
            );

            // Get current status
            $employeeStatus = BridgeEmployeeStatus::byNationalId($nationalId)
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // If no status record exists, create one using employee's current status
            if (!$employeeStatus) {
                $currentEmployeeStatus = $employee->employee_status ?: EmployeeStatus::ACTIVE;
                
                $employeeStatus = BridgeEmployeeStatus::create([
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                    'description' => 'Initial employee status record created',
                    'cby' => auth()->id(),
                    'cdate' => now(),
                    'statusdate' => now(),
                    'verified' => false,
                    'approved' => false,
                ]);

                Log::info('Created initial employee status record for termination request', [
                    'national_id' => $nationalId,
                    'employee_status' => $currentEmployeeStatus,
                ]);
            }

            // Create status record for termination request
            $newStatus = BridgeEmployeeStatus::create([
                'national_id' => $nationalId,
                'employee_status' => EmployeeStatus::PENDING_TERMINATION,
                'previous_employee_status' => $employeeStatus->employee_status,
                'description' => 'Employee termination submitted for approval',
                'cby' => auth()->id(),
                'cdate' => now(),
                'statusdate' => now(),
                'verified' => false,
                'approved' => false,
            ]);

            // Update employee status
            $employee->update([
                'employee_status' => EmployeeStatus::PENDING_TERMINATION,
            ]);

            DB::commit();

            Log::info('Employee termination submitted for approval', [
                'national_id' => $nationalId,
                'referral_request_id' => $referralRequest->id,
                'employee_status_id' => $newStatus->id,
            ]);

            return [
                'referral_request' => $referralRequest,
                'employee_status' => $newStatus,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to submit employee termination for approval', [
                'national_id' => $nationalId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Approve a referral request
     */
    public function approveReferralRequest(int $referralRequestId, ?string $remarks = null): bool
    {
        DB::beginTransaction();
        try {
            $referralRequest = ReferralRequest::findOrFail($referralRequestId);

            // Validate that maker cannot approve their own request
            if ($referralRequest->initiated_by == auth()->id()) {
                throw new Exception('A maker cannot approve their own request');
            }

            if (!$referralRequest->isPending()) {
                throw new Exception('Referral request is not pending');
            }

            // Update referral request
            $referralRequest->update([
                'status' => ReferralRequest::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'actioned_at' => now(),
                'remarks' => $remarks,
            ]);

            // Process approval based on action type
            switch ($referralRequest->action_type) {
                case ReferralRequest::ACTION_CREATE:
                    $this->approveCreation($referralRequest);
                    break;
                case ReferralRequest::ACTION_UPDATE:
                    $this->approveUpdate($referralRequest);
                    break;
                case ReferralRequest::ACTION_DELETE:
                    $this->approveDeletion($referralRequest);
                    break;
                case ReferralRequest::ACTION_TERMINATE:
                    $this->approveTermination($referralRequest);
                    break;
            }

            DB::commit();

            Log::info('Referral request approved', [
                'referral_request_id' => $referralRequestId,
                'action_type' => $referralRequest->action_type,
                'national_id' => $referralRequest->national_id,
                'approved_by' => auth()->id(),
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to approve referral request', [
                'referral_request_id' => $referralRequestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reject a referral request
     */
    public function rejectReferralRequest(int $referralRequestId, string $remarks): bool
    {
        DB::beginTransaction();
        try {
            $referralRequest = ReferralRequest::findOrFail($referralRequestId);

            // Validate that maker cannot reject their own request
            if ($referralRequest->initiated_by == auth()->id()) {
                throw new Exception('A maker cannot reject their own request');
            }

            if (!$referralRequest->isPending()) {
                throw new Exception('Referral request is not pending');
            }

            // Update referral request
            $referralRequest->update([
                'status' => ReferralRequest::STATUS_REJECTED,
                'approved_by' => auth()->id(),
                'actioned_at' => now(),
                'remarks' => $remarks,
            ]);

            // Get the latest employee status
            $employeeStatus = BridgeEmployeeStatus::byNationalId($referralRequest->national_id)
                ->orderBy('statusdate', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            // Restore previous status if exists
            if ($employeeStatus && $employeeStatus->previous_employee_status) {
                $employeeStatus->update([
                    'employee_status' => $employeeStatus->previous_employee_status,
                ]);

                $employee = BridgeEmployee::find($referralRequest->national_id);
                if ($employee) {
                    $employee->update([
                        'employee_status' => $employeeStatus->previous_employee_status,
                    ]);
                }
            } elseif ($employeeStatus && $employeeStatus->employee_status === EmployeeStatus::PENDING_APPROVAL) {
                // For creation requests, mark as rejected
                $employeeStatus->update([
                    'employee_status' => EmployeeStatus::REJECTED,
                ]);

                $employee = BridgeEmployee::find($referralRequest->national_id);
                if ($employee) {
                    $employee->update([
                        'employee_status' => EmployeeStatus::REJECTED,
                    ]);
                }
            }

            DB::commit();

            Log::info('Referral request rejected', [
                'referral_request_id' => $referralRequestId,
                'action_type' => $referralRequest->action_type,
                'national_id' => $referralRequest->national_id,
                'rejected_by' => auth()->id(),
                'remarks' => $remarks,
            ]);

            return true;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to reject referral request', [
                'referral_request_id' => $referralRequestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Approve employee creation
     */
    private function approveCreation(ReferralRequest $referralRequest): void
    {
        $employee = BridgeEmployee::findOrFail($referralRequest->national_id);
        $employeeStatus = BridgeEmployeeStatus::byNationalId($referralRequest->national_id)
            ->where('employee_status', EmployeeStatus::PENDING_APPROVAL)
            ->orderBy('statusdate', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$employeeStatus) {
            throw new Exception('Employee status record not found');
        }

        // Get employee's office from bridge_employment_details
        $employmentDetails = DB::connection('bcmis2')
            ->table('bridge_employment_details')
            ->where('national_id', $employee->national_id)
            ->orderBy('cdate', 'desc')
            ->first();

        $office = null;
        if ($employmentDetails && $employmentDetails->officeid) {
            $office = BridgeOffice::find($employmentDetails->officeid);
        }

        // Determine PF number based on office configuration
        $pfNumber = null;

        if ($office) {
            // Office is configured
            if ($office->auto_generate_pf) {
                // Office auto-generates PF - generate if not already set
                if (empty($employee->pfno)) {
                    $pfNumber = $this->generatePFNumber();
                    Log::info('PF number auto-generated for office', [
                        'office_id' => $office->id,
                        'office_name' => $office->office_name,
                        'pf_number' => $pfNumber,
                    ]);
                } else {
                    // PF already exists, use it
                    $pfNumber = $employee->pfno;
                    Log::info('Using existing PF number for auto-generate office', [
                        'office_id' => $office->id,
                        'pf_number' => $pfNumber,
                    ]);
                }
            } else {
                // Office requires manual PF entry
                if (empty($employee->pfno)) {
                    throw new Exception(
                        "Cannot approve employee creation. PF Number is required for {$office->office_name} office but was not provided. " .
                        "Please update the employee record with a valid PF number before approval."
                    );
                } else {
                    // Validate PF uniqueness
                    $existingEmployee = BridgeEmployee::where('pfno', $employee->pfno)
                        ->where('national_id', '!=', $employee->national_id)
                        ->first();

                    if ($existingEmployee) {
                        throw new Exception(
                            "PF Number '{$employee->pfno}' is already assigned to another employee. " .
                            "Please provide a unique PF number."
                        );
                    }

                    $pfNumber = $employee->pfno;
                    Log::info('Using provided PF number for manual-entry office', [
                        'office_id' => $office->id,
                        'office_name' => $office->office_name,
                        'pf_number' => $pfNumber,
                    ]);
                }
            }
        } else {
            // No office configured - default behavior: auto-generate if PF is missing
            if (empty($employee->pfno)) {
                $pfNumber = $this->generatePFNumber();
                Log::info('PF number auto-generated (no office configured)', [
                    'pf_number' => $pfNumber,
                ]);
            } else {
                $pfNumber = $employee->pfno;
                Log::info('Using existing PF number (no office configured)', [
                    'pf_number' => $pfNumber,
                ]);
            }
        }

        // Update employee status to ACTIVE
        $employeeStatus->update([
            'employee_status' => EmployeeStatus::ACTIVE,
            'approved_by' => auth()->id(),
            'approve_date' => now(),
            'approved' => true,
        ]);

        // Update employee with PF number and ACTIVE status
        $employee->update([
            'employee_status' => EmployeeStatus::ACTIVE,
            'pfno' => $pfNumber,
            'account_status' => 'Active',
        ]);

        // Update auth_user table with PF number if a matching user exists
        // Try to find auth_user by nida (national_id), email, phone, or username
        $authUser = AuthUser::where(function ($query) use ($employee) {
            $query->where('nida', $employee->national_id)
                ->orWhere('email', $employee->email)
                ->orWhere('phone', $employee->mobile)
                ->orWhere('username', $employee->username);
        })->first();

        if ($authUser) {
            $authUser->update(['pf_number' => $pfNumber]);
            Log::info('Auth user updated with PF number', [
                'auth_user_id' => $authUser->id,
                'pf_number' => $pfNumber,
            ]);
        } else {
            Log::warning('No matching auth_user found for employee', [
                'national_id' => $referralRequest->national_id,
                'employee_email' => $employee->email,
                'employee_mobile' => $employee->mobile,
                'employee_username' => $employee->username,
            ]);
        }

        try {
            app(MandatoryDeductionProvisioner::class)->syncForEmployee($employee->national_id);
        } catch (\Exception $e) {
            Log::warning('Failed to sync mandatory deductions after employee activation', [
                'national_id' => $employee->national_id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Employee creation approved', [
            'national_id' => $referralRequest->national_id,
            'pf_number' => $pfNumber,
            'office' => $office ? $office->office_name : 'Not configured',
        ]);
    }

    /**
     * Approve employee update
     */
    private function approveUpdate(ReferralRequest $referralRequest): void
    {
        $employee = BridgeEmployee::findOrFail($referralRequest->national_id);
        $employeeStatus = BridgeEmployeeStatus::byNationalId($referralRequest->national_id)
            ->where('employee_status', EmployeeStatus::PENDING_UPDATE)
            ->orderBy('statusdate', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$employeeStatus || !$employeeStatus->proposed_changes) {
            throw new Exception('Employee status record with proposed changes not found');
        }

        // Apply proposed changes
        $proposedChanges = $employeeStatus->proposed_changes;
        $updateData = [];
        foreach ($proposedChanges as $field => $value) {
            if (in_array($field, $employee->getFillable())) {
                $updateData[$field] = $value;
            }
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
        }

        // Employee remains active after update approval — store canonical code A
        $employeeStatus->update([
            'employee_status' => EmployeeStatus::ACTIVE,
            'approved_by' => auth()->id(),
            'approve_date' => now(),
            'approved' => true,
        ]);

        $employee->update([
            'employee_status' => EmployeeStatus::ACTIVE,
        ]);

        if (array_key_exists('basicsalary', $updateData)) {
            try {
                app(MandatoryDeductionProvisioner::class)->recalculateForEmployee($employee->national_id);
            } catch (\Exception $e) {
                Log::warning('Failed to recalculate payroll assignments after salary update', [
                    'national_id' => $employee->national_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Employee update approved', [
            'national_id' => $referralRequest->national_id,
            'fields_updated' => array_keys($updateData),
        ]);
    }

    /**
     * Approve employee deletion
     */
    private function approveDeletion(ReferralRequest $referralRequest): void
    {
        $employee = BridgeEmployee::findOrFail($referralRequest->national_id);
        $employeeStatus = BridgeEmployeeStatus::byNationalId($referralRequest->national_id)
            ->where('employee_status', EmployeeStatus::PENDING_DELETION)
            ->orderBy('statusdate', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$employeeStatus) {
            throw new Exception('Employee status record not found');
        }

        // Update status to DELETED
        $employeeStatus->update([
            'employee_status' => EmployeeStatus::DELETED,
            'approved_by' => auth()->id(),
            'approve_date' => now(),
            'approved' => true,
        ]);

        // Mark employee as deleted (soft delete or status update based on requirements)
        $employee->update([
            'employee_status' => EmployeeStatus::DELETED,
            'account_status' => 'Inactive',
        ]);

        // Optionally: $employee->delete(); if hard delete is required

        Log::info('Employee deletion approved', [
            'national_id' => $referralRequest->national_id,
        ]);
    }

    /**
     * Approve employee termination
     */
    private function approveTermination(ReferralRequest $referralRequest): void
    {
        $employee = BridgeEmployee::findOrFail($referralRequest->national_id);
        $employeeStatus = BridgeEmployeeStatus::byNationalId($referralRequest->national_id)
            ->where('employee_status', EmployeeStatus::PENDING_TERMINATION)
            ->orderBy('statusdate', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$employeeStatus) {
            throw new Exception('Employee status record not found');
        }

        // Update status to TERMINATED
        $employeeStatus->update([
            'employee_status' => EmployeeStatus::TERMINATED,
            'approved_by' => auth()->id(),
            'approve_date' => now(),
            'approved' => true,
        ]);

        // Update employee to TERMINATED
        $employee->update([
            'employee_status' => EmployeeStatus::TERMINATED,
            'account_status' => 'Inactive',
        ]);

        Log::info('Employee termination approved', [
            'national_id' => $referralRequest->national_id,
        ]);
    }

    /**
     * Find referral request by employee status ID
     */
    public function findReferralRequestByStatusId(int $statusId): ?ReferralRequest
    {
        $employeeStatus = BridgeEmployeeStatus::find($statusId);
        
        if (!$employeeStatus) {
            return null;
        }

        // Map employee status to action type
        $actionTypeMap = [
            EmployeeStatus::PENDING_APPROVAL => ReferralRequest::ACTION_CREATE,
            EmployeeStatus::PENDING_CREATION => ReferralRequest::ACTION_CREATE,
            EmployeeStatus::PENDING_UPDATE => ReferralRequest::ACTION_UPDATE,
            EmployeeStatus::PENDING_DELETION => ReferralRequest::ACTION_DELETE,
            EmployeeStatus::PENDING_TERMINATION => ReferralRequest::ACTION_TERMINATE,
        ];

        $actionType = $actionTypeMap[$employeeStatus->employee_status] ?? null;

        if (!$actionType) {
            return null;
        }

        // Find pending referral request for this national_id and action_type
        return ReferralRequest::byNationalId($employeeStatus->national_id)
            ->byActionType($actionType)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Approve referral request by employee status ID (for backward compatibility)
     */
    public function approveByStatusId(int $statusId, ?string $remarks = null): bool
    {
        $referralRequest = $this->findReferralRequestByStatusId($statusId);

        if (!$referralRequest) {
            // If no referral request found, check the status record's creator
            $employeeStatus = BridgeEmployeeStatus::find($statusId);
            if ($employeeStatus && $employeeStatus->cby == auth()->id()) {
                throw new Exception('A maker cannot approve their own request');
            }
            throw new Exception('Referral request not found for the given status ID');
        }

        return $this->approveReferralRequest($referralRequest->id, $remarks);
    }

    /**
     * Reject referral request by employee status ID (for backward compatibility)
     */
    public function rejectByStatusId(int $statusId, string $remarks): bool
    {
        $referralRequest = $this->findReferralRequestByStatusId($statusId);

        if (!$referralRequest) {
            // If no referral request found, check the status record's creator
            $employeeStatus = BridgeEmployeeStatus::find($statusId);
            if ($employeeStatus && $employeeStatus->cby == auth()->id()) {
                throw new Exception('A maker cannot reject their own request');
            }
            throw new Exception('Referral request not found for the given status ID');
        }

        return $this->rejectReferralRequest($referralRequest->id, $remarks);
    }

    /**
     * Generate a unique PF number
     * Uses pattern NB0001, NB0002, etc.
     */
    private function generatePFNumber(): string
    {
        $prefix = 'NB';
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // Get all existing PFNOs that start with 'NB' and extract their numeric parts
            $existingPFNOs = BridgeEmployee::whereNotNull('pfno')
                ->where('pfno', 'like', $prefix . '%')
                ->pluck('pfno')
                ->map(function ($pfno) use ($prefix) {
                    $numericPart = substr($pfno, strlen($prefix));
                    return is_numeric($numericPart) ? (int)$numericPart : 0;
                })
                ->filter(function ($num) {
                    return $num > 0;
                })
                ->toArray();

            // Also check PFNO2 field
            $existingPFNO2s = BridgeEmployee::whereNotNull('pfno2')
                ->where('pfno2', 'like', $prefix . '%')
                ->pluck('pfno2')
                ->map(function ($pfno) use ($prefix) {
                    $numericPart = substr($pfno, strlen($prefix));
                    return is_numeric($numericPart) ? (int)$numericPart : 0;
                })
                ->filter(function ($num) {
                    return $num > 0;
                })
                ->toArray();

            $allNumbers = array_merge($existingPFNOs, $existingPFNO2s);
            $nextNumber = empty($allNumbers) ? 1 : max($allNumbers) + 1;

            $newPFNO = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // Check if this PFNO already exists (in case of race condition)
            $exists = BridgeEmployee::where('pfno', $newPFNO)
                ->orWhere('pfno2', $newPFNO)
                ->exists();

            if (!$exists) {
                return $newPFNO;
            }
        }

        // Fallback: use timestamp-based PFNO if max attempts reached
        $fallbackPFNO = $prefix . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
        Log::warning('Max attempts reached for PF number generation, using fallback', [
            'fallback_pfno' => $fallbackPFNO
        ]);

        return $fallbackPFNO;
    }
}

