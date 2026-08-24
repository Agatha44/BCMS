<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\SpecialTask;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\BridgeEmployeeRole;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SpecialTaskController extends BasicController
{
    /**
     * Get employee national ID from auth_user.
     */
    private function getNationalIdFromUserId($userId): ?string
    {
        try {
            $user = AuthUser::find($userId);

            return ($user && !empty($user->nida)) ? $user->nida : null;
        } catch (\Exception $e) {
            Log::warning('Failed to get national ID from user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get employee PF number from auth_user.
     */
    private function getPfNumberFromUserId($userId): ?string
    {
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

    /**
     * Check if current user has approver/validator/reviewer privileges
     * Roles: 'employee approver', 'overtime validator', 'overtime reviewer'
     */
    private function hasApproverRole($userId = null): bool
    {
        $currentUserId = $userId ?? auth()->id();
        if (!$currentUserId) {
            return false;
        }

        $approverRoles = ['employee approver', 'overtime validator', 'overtime reviewer'];

        try {
            $nationalId = $this->getNationalIdFromUserId($currentUserId);
            if (!$nationalId) {
                return false;
            }

            $bridgeEmployeeRoles = BridgeEmployeeRole::where('national_id', $nationalId)
                ->active()
                ->with('role')
                ->get();

            foreach ($bridgeEmployeeRoles as $bridgeEmployeeRole) {
                if ($bridgeEmployeeRole->role) {
                    $roleName = strtolower($bridgeEmployeeRole->role->role_name ?? '');
                    if (in_array($roleName, $approverRoles, true)) {
                        return true;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to check approver role', [
                'user_id' => $currentUserId,
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    /**
     * List all special tasks with filtering and pagination
     */
public function index(Request $request): JsonResponse
    {
        try {
            $query = SpecialTask::query();
            $userId = auth()->id();
            $pfNumber = $this->getPfNumberFromUserId($userId);
            $hasApproverRole = $this->hasApproverRole($userId);

            // Filter by PF number (if provided and user has permission)
            if ($request->has('pf_number')) {
                // If user has approver role, they can view any employee's tasks
                // Otherwise, they can only view their own tasks
                if (!$hasApproverRole && $pfNumber && $request->pf_number !== $pfNumber) {
                    return $this->sendError('You do not have permission to view other employees\' tasks.', [], 0, 403);
                }
                $query->where('pf_number', $request->pf_number);
            } else {
                // If no PF number provided, check if user has approver role
                if (!$hasApproverRole) {
                    // Regular employee: show only their tasks
                    if ($pfNumber) {
                        $query->where('pf_number', $pfNumber);
                    } else {
                        // No PF number found, return empty result
                        $query->whereRaw('1 = 0');
                    }
                }
                // Approvers can view all tasks (no filter applied)
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->where('end_date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->where('start_date', '<=', $request->end_date);
            }

            // Filter by overtime rule
            if ($request->has('overtime_rule') && $request->overtime_rule) {
                $query->where('overtime_rule', $request->overtime_rule);
            }

            // Search by task name
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('task_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $tasks = $query->paginate($perPage);

            // Add employee information
            $tasks->getCollection()->transform(function ($task) {
                $employee = BridgeEmployee::where('pfno', $task->pf_number)
                    ->orWhere('pfno2', $task->pf_number)
                    ->first();
                
                $task->employee_name = $employee 
                    ? trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? ''))
                    : null;
                
                return $task;
            });

            return $this->sendResponse($tasks, 'Special tasks retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve special tasks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve special tasks', [], 0, 500);
        }
    }

    /**
     * Get a specific special task by ID
     */
    public function show($id): JsonResponse
    {
        try {
            $task = SpecialTask::find($id);
            
            if (!$task) {
                return $this->sendError('Special task not found', [], 0, 404);
            }

            // Add employee information
            $employee = BridgeEmployee::where('pfno', $task->pf_number)
                ->orWhere('pfno2', $task->pf_number)
                ->first();
            
            $task->employee_name = $employee 
                ? trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? ''))
                : null;

            return $this->sendResponse($task, 'Special task retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve special task', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to retrieve special task', [], 0, 500);
        }
    }

    /**
     * Create a new special task (Manager assigns to employee)
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'pf_number' => 'required|string|max:50',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'overtime_rule' => 'required|in:all_hours,standard,custom',
                'custom_overtime_threshold' => 'nullable|numeric|min:0|required_if:overtime_rule,custom',
                'is_pre_approved' => 'nullable|boolean',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Verify employee exists
            $employee = BridgeEmployee::where('pfno', $request->pf_number)
                ->first();

            if (!$employee) {
                return $this->sendError('Employee not found with the provided PF number', [], 0, 404);
            }

            DB::connection('bcmis2')->beginTransaction();

            $task = SpecialTask::create([
                'task_name' => $request->task_name,
                'description' => $request->description,
                'pf_number' => $request->pf_number,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'overtime_rule' => $request->overtime_rule,
                'custom_overtime_threshold' => $request->custom_overtime_threshold,
                'is_pre_approved' => $request->boolean('is_pre_approved', false),
                'status' => 'active',
                'notes' => $request->notes,
                'created_by' => (string) auth()->id(),
            ]);

            DB::connection('bcmis2')->commit();

            // Add employee information to response
            $task->employee_name = trim(($employee->fname ?? '') . ' ' . ($employee->mname ?? '') . ' ' . ($employee->sname ?? ''));

            return $this->sendResponse($task, 'Special task created successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to create special task', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to create special task: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Employee applies for special task (creates pending application)
     */
    public function apply(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'overtime_rule' => 'required|in:all_hours,standard,custom',
                'custom_overtime_threshold' => 'nullable|numeric|min:0|required_if:overtime_rule,custom',
                'notes' => 'nullable|string',
                'justification' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $userId = auth()->id();
            if (!$userId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            $pfNumber = $this->getPfNumberFromUserId($userId);
            if (!$pfNumber) {
                return $this->sendError('PF number not found for user. Please ensure your account is linked to an employee record.', [], 0, 400);
            }

            DB::connection('bcmis2')->beginTransaction();

            $task = SpecialTask::create([
                'task_name' => $request->task_name,
                'description' => $request->description ?? $request->justification,
                'pf_number' => $pfNumber,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'overtime_rule' => $request->overtime_rule,
                'custom_overtime_threshold' => $request->custom_overtime_threshold,
                'is_pre_approved' => false, // Employee applications are not pre-approved
                'status' => 'pending', // Requires manager approval
                'notes' => $request->notes ?? $request->justification,
                'created_by' => (string) $userId,
            ]);

            DB::connection('bcmis2')->commit();

            return $this->sendResponse($task, 'Special task application submitted successfully. Waiting for manager approval.');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to submit special task application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return $this->sendError('Failed to submit special task application: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update a special task
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $task = SpecialTask::find($id);
            
            if (!$task) {
                return $this->sendError('Special task not found', [], 0, 404);
            }

            // Check if task can be edited (not completed)
            if ($task->status === 'completed') {
                return $this->sendError('Cannot edit completed tasks', [], 0, 400);
            }

            $validator = Validator::make($request->all(), [
                'task_name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after_or_equal:start_date',
                'overtime_rule' => 'sometimes|required|in:all_hours,standard,custom',
                'custom_overtime_threshold' => 'nullable|numeric|min:0|required_if:overtime_rule,custom',
                'is_pre_approved' => 'nullable|boolean',
                'status' => 'sometimes|required|in:pending,active,completed,cancelled',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            $updateData = array_filter($request->only([
                'task_name',
                'description',
                'start_date',
                'end_date',
                'overtime_rule',
                'custom_overtime_threshold',
                'is_pre_approved',
                'status',
                'notes',
            ]), function ($value) {
                return $value !== null;
            });

            $updateData['modified_by'] = (string) auth()->id();
            $updateData['modified_at'] = now();

            $task->update($updateData);

            DB::connection('bcmis2')->commit();

            return $this->sendResponse($task->fresh(), 'Special task updated successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to update special task', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update special task: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Approve employee's special task application (Approver action)
     */
    public function approve(Request $request, $id): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // Check if user has approver role
            if (!$this->hasApproverRole($userId)) {
                return $this->sendError('You do not have permission to approve special tasks.', [], 0, 403);
            }

            $task = SpecialTask::find($id);
            
            if (!$task) {
                return $this->sendError('Special task not found', [], 0, 404);
            }

            // Prevent users from approving their own requests
            $currentUserPfNumber = $this->getPfNumberFromUserId($userId);
            if ($currentUserPfNumber && $task->pf_number === $currentUserPfNumber) {
                return $this->sendError('You cannot approve your own special task request.', [], 0, 403);
            }

            if ($task->status !== 'pending') {
                return $this->sendError('Only pending tasks can be approved', [], 0, 400);
            }

            $validator = Validator::make($request->all(), [
                'comment' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            $task->update([
                'status' => 'active',
                'notes' => $request->comment ? ($task->notes . "\n\nApprover Approval: " . $request->comment) : $task->notes,
                'modified_by' => (string) auth()->id(),
                'modified_at' => now(),
            ]);

            DB::connection('bcmis2')->commit();

            return $this->sendResponse($task->fresh(), 'Special task application approved successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to approve special task', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to approve special task: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Reject employee's special task application (Approver action)
     */
    public function reject(Request $request, $id): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // Check if user has approver role
            if (!$this->hasApproverRole($userId)) {
                return $this->sendError('You do not have permission to reject special tasks.', [], 0, 403);
            }

            $task = SpecialTask::find($id);
            
            if (!$task) {
                return $this->sendError('Special task not found', [], 0, 404);
            }

            // Prevent users from rejecting their own requests
            $currentUserPfNumber = $this->getPfNumberFromUserId($userId);
            if ($currentUserPfNumber && $task->pf_number === $currentUserPfNumber) {
                return $this->sendError('You cannot reject your own special task request.', [], 0, 403);
            }

            if ($task->status !== 'pending') {
                return $this->sendError('Only pending tasks can be rejected', [], 0, 400);
            }

            $validator = Validator::make($request->all(), [
                'comment' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            $task->update([
                'status' => 'cancelled',
                'notes' => $task->notes . "\n\nApprover Rejection: " . $request->comment,
                'modified_by' => (string) auth()->id(),
                'modified_at' => now(),
            ]);

            DB::connection('bcmis2')->commit();

            return $this->sendResponse($task->fresh(), 'Special task application rejected');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to reject special task', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to reject special task: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Delete a special task
     */
    public function destroy($id): JsonResponse
    {
        try {
            $task = SpecialTask::find($id);
            
            if (!$task) {
                return $this->sendError('Special task not found', [], 0, 404);
            }

            // Check if task has related overtime requests
            $hasOvertimeRequests = DB::connection('bcmis2')
                ->table('overtime_request_days')
                ->where('special_task_id', $id)
                ->exists();

            if ($hasOvertimeRequests) {
                return $this->sendError('Cannot delete task that has related overtime requests. Please cancel it instead.', [], 0, 400);
            }

            DB::connection('bcmis2')->beginTransaction();

            $task->delete();

            DB::connection('bcmis2')->commit();

            return $this->sendResponse(null, 'Special task deleted successfully');
        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to delete special task', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to delete special task: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get active tasks for a specific employee
     */
    public function getEmployeeTasks(Request $request, $pfNumber = null): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // If no PF number provided, use logged-in user's PF number
            if (!$pfNumber) {
                $pfNumber = $this->getPfNumberFromUserId($userId);
                if (!$pfNumber) {
                    return $this->sendError('PF number not found for user', [], 0, 400);
                }
            }

            $query = SpecialTask::where('pf_number', $pfNumber);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            } else {
                // Default: show active and pending
                $query->whereIn('status', ['active', 'pending']);
            }

            // Filter by date (tasks that overlap with date range)
            if ($request->has('date')) {
                $date = $request->date;
                $query->where('start_date', '<=', $date)
                      ->where('end_date', '>=', $date);
            }

            $tasks = $query->orderBy('start_date', 'desc')->get();

            return $this->sendResponse($tasks, 'Employee tasks retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve employee tasks', [
                'pf_number' => $pfNumber,
                'error' => $e->getMessage()
            ]);
            return $this->sendError('Failed to retrieve employee tasks', [], 0, 500);
        }
    }
}
