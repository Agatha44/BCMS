<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\AuthUserRole;
use App\Services\Administration\AuthRoleModuleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class BridgeModuleRoleController extends BasicController
{
    private AuthRoleModuleService $authRoleModuleService;

    public function __construct(AuthRoleModuleService $authRoleModuleService)
    {
        $this->authRoleModuleService = $authRoleModuleService;
    }

    /**
     * Display a listing of module-role assignments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $moduleId = $request->input('module_id');
            $roleId = $request->input('role_id');
            $isActive = $request->input('is_active');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Apply module filter
            if ($moduleId) {
                $query->where('bridge_module_role.module_id', $moduleId);
            }

            // Apply role filter
            if ($roleId) {
                $query->where('bridge_module_role.role_id', $roleId);
            }

            // Apply active filter
            if ($isActive !== null) {
                $query->where('bridge_module_role.is_active', $isActive);
            }

            // Apply sorting
            $query->orderBy('bridge_module_role.' . $sortBy, $sortOrder);

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->input('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $assignments = $query->offset($offset)->limit($perPage)->get();

            return $this->sendResponse([
                'assignments' => $assignments,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 'Module-role assignments retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching module-role assignments: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve module-role assignments: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all active module-role assignments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getActive(Request $request): JsonResponse
    {
        try {
            $moduleId = $request->input('module_id');
            $roleId = $request->input('role_id');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.is_active', true)
                ->where('bridge_module.is_active', true)
                ->where('roles.is_active', true);

            // Apply module filter
            if ($moduleId) {
                $query->where('bridge_module_role.module_id', $moduleId);
            }

            // Apply role filter
            if ($roleId) {
                $query->where('bridge_module_role.role_id', $roleId);
            }

            // Apply sorting
            $query->orderBy('bridge_module_role.' . $sortBy, $sortOrder);

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->input('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $assignments = $query->offset($offset)->limit($perPage)->get();

            return $this->sendResponse([
                'assignments' => $assignments,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 'Active module-role assignments retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active module-role assignments: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve active module-role assignments: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Store a newly created module-role assignment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'module_id' => 'required|integer|exists:bcmis2.bridge_module,id',
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if assignment already exists
            $existing = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->where('module_id', $request->module_id)
                ->where('role_id', $request->role_id)
                ->first();

            if ($existing) {
                // If exists but inactive, activate it
                if (!$existing->is_active) {
                    DB::connection('bcmis2')
                        ->table('bridge_module_role')
                        ->where('id', $existing->id)
                        ->update([
                            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                            'modified_by' => auth()->id(),
                            'modified_at' => now(),
                        ]);

                    $assignment = DB::connection('bcmis2')
                        ->table('bridge_module_role')
                        ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                        ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                        ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                        ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                        ->select(
                            'bridge_module_role.*',
                            'bridge_module.title as module_name',
                            'bridge_module.icon as module_icon',
                            'bridge_module.module_id as module_identifier',
                            'roles.role_name',
                            'roles.role_description',
                            DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                            DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                        )
                        ->where('bridge_module_role.id', $existing->id)
                        ->first();

                    Log::info('Module-role assignment reactivated successfully', [
                        'assignment_id' => $existing->id,
                        'module_id' => $request->module_id,
                        'role_id' => $request->role_id,
                        'modified_by' => auth()->id()
                    ]);

                    return $this->sendResponse($assignment, 'Module access activated for role');
                }

                return $this->sendError('This module-role assignment already exists', [
                    'assignment_id' => $existing->id,
                    'module_id' => $request->module_id,
                    'role_id' => $request->role_id
                ], 0, 409);
            }

            // Verify module exists
            $module = DB::connection('bcmis2')->table('bridge_module')->where('id', $request->module_id)->first();
            if (!$module) {
                return $this->sendError('Module not found', [], 0, 404);
            }

            // Verify role exists
            $role = DB::connection('bcmis2')->table('roles')->where('id', $request->role_id)->first();
            if (!$role) {
                return $this->sendError('Role not found', [], 0, 404);
            }

            $assignmentData = [
                'module_id' => $request->module_id,
                'role_id' => $request->role_id,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            $id = DB::connection('bcmis2')->table('bridge_module_role')->insertGetId($assignmentData);

            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.id', $id)
                ->first();

            Log::info('Module-role assignment created successfully', [
                'assignment_id' => $id,
                'module_id' => $request->module_id,
                'role_id' => $request->role_id,
                'created_by' => auth()->id()
            ]);

            return $this->sendResponse($assignment, 'Module-role assignment created successfully');

        } catch (\Exception $e) {
            Log::error('Error creating module-role assignment: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create module-role assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified module-role assignment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.id', $id)
                ->first();

            if (!$assignment) {
                return $this->sendError('Module-role assignment not found', [], 0, 404);
            }

            return $this->sendResponse($assignment, 'Module-role assignment retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching module-role assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve module-role assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update the specified module-role assignment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'module_id' => 'nullable|integer|exists:bcmis2.bridge_module,id',
                'role_id' => 'nullable|integer|exists:bcmis2.roles,id',
                'is_active' => 'nullable|boolean',
                'modified_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if assignment exists
            $assignment = DB::connection('bcmis2')->table('bridge_module_role')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Module-role assignment not found', [], 0, 404);
            }

            // Check for duplicate if module_id or role_id is being updated
            if ($request->has('module_id') || $request->has('role_id')) {
                $newModuleId = $request->has('module_id') ? $request->module_id : $assignment->module_id;
                $newRoleId = $request->has('role_id') ? $request->role_id : $assignment->role_id;

                $duplicate = DB::connection('bcmis2')
                    ->table('bridge_module_role')
                    ->where('module_id', $newModuleId)
                    ->where('role_id', $newRoleId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->sendError('This module-role assignment already exists', [
                        'existing_assignment_id' => $duplicate->id
                    ], 0, 409);
                }
            }

            $updateData = [
                'modified_by' => $request->modified_by,
                'modified_at' => now(),
            ];

            if ($request->has('module_id')) {
                $updateData['module_id'] = $request->module_id;
            }

            if ($request->has('role_id')) {
                $updateData['role_id'] = $request->role_id;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = (bool) $request->is_active;
            }

            DB::connection('bcmis2')->table('bridge_module_role')
                ->where('id', $id)
                ->update($updateData);

            $updatedAssignment = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.id', $id)
                ->first();

            Log::info('Module-role assignment updated successfully', [
                'assignment_id' => $id,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedAssignment, 'Module-role assignment updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating module-role assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to update module-role assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove the specified module-role assignment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $assignment = DB::connection('bcmis2')->table('bridge_module_role')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Module-role assignment not found', [], 0, 404);
            }

            DB::connection('bcmis2')->table('bridge_module_role')->where('id', $id)->delete();

            Log::info('Module-role assignment deleted successfully', [
                'assignment_id' => $id
            ]);

            return $this->sendResponse(null, 'Module-role assignment deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting module-role assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to delete module-role assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Revoke a module from a role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revokeModuleFromRole(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'module_id' => 'required|integer|exists:bcmis2.bridge_module,id',
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->where('module_id', $request->module_id)
                ->where('role_id', $request->role_id)
                ->first();

            if (!$assignment) {
                return $this->sendError('Module is not assigned to this role', [], 0, 404);
            }

            // Delete the assignment
            DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->where('id', $assignment->id)
                ->delete();

            Log::info('Module revoked from role successfully', [
                'assignment_id' => $assignment->id,
                'module_id' => $request->module_id,
                'role_id' => $request->role_id
            ]);

            return $this->sendResponse(null, 'Module revoked from role successfully');

        } catch (\Exception $e) {
            Log::error('Error revoking module from role: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to revoke module from role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all modules assigned to a role.
     *
     * @param int $roleId
     * @return JsonResponse
     */
    public function getModulesByRole($roleId): JsonResponse
    {
        try {
            // Verify role exists
            $role = DB::connection('bcmis2')->table('roles')->where('id', $roleId)->first();
            if (!$role) {
                return $this->sendError('Role not found', [], 0, 404);
            }

            $modules = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'bridge_module.description as module_description',
                    'bridge_module.is_active as module_is_active'
                )
                ->where('bridge_module_role.role_id', $roleId)
                ->where('bridge_module_role.is_active', true)
                ->orderBy('bridge_module.title', 'asc')
                ->get();

            return $this->sendResponse([
                'role' => $role,
                'modules' => $modules,
                'total_modules' => $modules->count()
            ], 'Modules for role retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching modules by role: ' . $e->getMessage(), [
                'role_id' => $roleId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve modules for role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all roles that have access to a module.
     * Accepts either numeric ID or module identifier (e.g., "employee-management").
     *
     * @param string|int $moduleId
     * @return JsonResponse
     */
    public function getRolesByModule($moduleId): JsonResponse
    {
        try {
            // Check if $moduleId is numeric (ID) or string (module identifier)
            $isNumeric = is_numeric($moduleId);

            // Verify module exists - try by ID first if numeric, otherwise by module_id (identifier)
            if ($isNumeric) {
                $module = DB::connection('bcmis2')->table('bridge_module')->where('id', $moduleId)->first();
            } else {
                $module = DB::connection('bcmis2')->table('bridge_module')->where('module_id', $moduleId)->first();
            }

            if (!$module) {
                return $this->sendError('Module not found', [], 0, 404);
            }

            // Use the numeric ID from the module record for the join
            $actualModuleId = $module->id;

            // Use the actual numeric ID for the join
            $roles = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->select(
                    'bridge_module_role.*',
                    'roles.role_name',
                    'roles.role_description',
                    'roles.is_active as role_is_active'
                )
                ->where('bridge_module_role.module_id', $actualModuleId)
                ->where('bridge_module_role.is_active', true)
                ->orderBy('roles.role_name', 'asc')
                ->get();

            return $this->sendResponse([
                'module' => $module,
                'roles' => $roles,
                'total_roles' => $roles->count()
            ], 'Roles with module access retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching roles by module: ' . $e->getMessage(), [
                'module_id' => $moduleId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve roles for module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle the active status of a module-role assignment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'modified_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $assignment = DB::connection('bcmis2')->table('bridge_module_role')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Module-role assignment not found', [], 0, 404);
            }

            $newStatus = !$assignment->is_active;

            DB::connection('bcmis2')->table('bridge_module_role')
                ->where('id', $id)
                ->update([
                    'is_active' => $newStatus,
                    'modified_by' => $request->modified_by,
                    'modified_at' => now(),
                ]);

            $updatedAssignment = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.id', $id)
                ->first();

            Log::info('Module-role assignment status toggled', [
                'assignment_id' => $id,
                'new_status' => $newStatus,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedAssignment, 'Module-role assignment status updated successfully');

        } catch (\Exception $e) {
            Log::error('Error toggling module-role assignment status: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to toggle module-role assignment status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Bulk assign roles to a module.
     * Assigns one module to multiple roles (module first, then roles).
     * Returns records joined with bridge_module and roles (module_name, role_name).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkAssignModules(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'module_id' => 'required|integer|exists:bcmis2.bridge_module,id',
                'role_ids' => 'required|array',
                'role_ids.*' => 'integer|exists:bcmis2.roles,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $moduleId = $request->module_id;
            $roleIds = array_unique($request->role_ids);
            $createdBy = auth()->id();

            // Verify module exists
            $module = DB::connection('bcmis2')->table('bridge_module')->where('id', $moduleId)->first();
            if (!$module) {
                return $this->sendError('Module not found', [], 0, 404);
            }

            // Get existing assignments for this module
            $existing = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->where('module_id', $moduleId)
                ->whereIn('role_id', $roleIds)
                ->pluck('role_id')
                ->toArray();

            $newRoleIds = array_diff($roleIds, $existing);
            $alreadyAssigned = count($existing);
            $newlyAssigned = 0;

            if (!empty($newRoleIds)) {
                $insertData = [];
                foreach ($newRoleIds as $roleId) {
                    $insertData[] = [
                        'module_id' => $moduleId,
                        'role_id' => $roleId,
                        'is_active' => true,
                        'created_by' => $createdBy,
                        'created_at' => now(),
                    ];
                }

                DB::connection('bcmis2')->table('bridge_module_role')->insert($insertData);
                $newlyAssigned = count($insertData);
            }

            // Fetch all affected assignments with joins
            $assignments = DB::connection('bcmis2')
                ->table('bridge_module_role')
                ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role.modified_by')
                ->select(
                    'bridge_module_role.*',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon',
                    'bridge_module.module_id as module_identifier',
                    'roles.role_name',
                    'roles.role_description',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role.module_id', $moduleId)
                ->whereIn('bridge_module_role.role_id', $roleIds)
                ->get();

            Log::info('Bulk role assignment to module completed', [
                'module_id' => $moduleId,
                'total_requested' => count($roleIds),
                'already_assigned' => $alreadyAssigned,
                'newly_assigned' => $newlyAssigned
            ]);

            return $this->sendResponse([
                'module_id' => $moduleId,
                'module_name' => $module->title ?? null,
                'total_requested' => count($roleIds),
                'already_assigned' => $alreadyAssigned,
                'newly_assigned' => $newlyAssigned,
                'skipped' => $alreadyAssigned,
                'assignments' => $assignments,
            ], 'Bulk role assignment to module completed successfully');

        } catch (\Exception $e) {
            Log::error('Error in bulk role assignment to module: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to bulk assign roles to module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get modules accessible to the authenticated user via auth_user_role + auth_role_module,
     * and/or bridge_employee_role + bridge_module_role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserAccessibleModules(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            $nationalId = $user->nida ?? null;

            $authRoleIds = AuthUserRole::query()
                ->currentlyEffective()
                ->where('user_id', $user->id)
                ->pluck('role_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $authRoleModules = $this->authRoleModuleService
                ->getAccessibleModulesForAuthRoleIds($authRoleIds)
                ->map(fn ($module) => [
                    'id' => $module->id,
                    'module_name' => $module->module_name,
                    'module_icon' => $module->module_icon,
                    'module_identifier' => $module->module_identifier,
                    'module_description' => $module->module_description,
                    'module_is_active' => (bool) $module->module_is_active,
                    'source' => 'auth_role',
                    'auth_role_ids' => [(int) $module->auth_role_id],
                    'accessible_roles' => [],
                    'role_ids' => [],
                ]);

            $employeeRoleIds = [];
            $employeeModules = collect();

            if ($nationalId) {
                $employeeRoleIds = DB::connection('bcmis2')
                    ->table('bridge_employee_role')
                    ->where('national_id', $nationalId)
                    ->where('is_active', true)
                    ->pluck('role_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($employeeRoleIds !== []) {
                    $employeeModules = DB::connection('bcmis2')
                        ->table('bridge_module_role')
                        ->leftJoin('bridge_module', 'bridge_module_role.module_id', '=', 'bridge_module.id')
                        ->leftJoin('roles', 'bridge_module_role.role_id', '=', 'roles.id')
                        ->select(
                            'bridge_module.id',
                            'bridge_module.title as module_name',
                            'bridge_module.icon as module_icon',
                            'bridge_module.module_id as module_identifier',
                            'bridge_module.description as module_description',
                            'bridge_module.is_active as module_is_active',
                            'bridge_module_role.role_id',
                            'roles.role_name'
                        )
                        ->whereIn('bridge_module_role.role_id', $employeeRoleIds)
                        ->where('bridge_module_role.is_active', true)
                        ->where('bridge_module.is_active', true)
                        ->where('roles.is_active', true)
                        ->distinct()
                        ->orderBy('bridge_module.title', 'asc')
                        ->get()
                        ->groupBy('id')
                        ->map(function ($moduleGroup) {
                            $firstModule = $moduleGroup->first();

                            return [
                                'id' => $firstModule->id,
                                'module_name' => $firstModule->module_name,
                                'module_icon' => $firstModule->module_icon,
                                'module_identifier' => $firstModule->module_identifier,
                                'module_description' => $firstModule->module_description,
                                'module_is_active' => (bool) $firstModule->module_is_active,
                                'source' => 'employee_role',
                                'auth_role_ids' => [],
                                'accessible_roles' => $moduleGroup->pluck('role_name')->unique()->values()->toArray(),
                                'role_ids' => $moduleGroup->pluck('role_id')->unique()->values()->toArray(),
                            ];
                        })
                        ->values();
                }
            }

            $groupedModules = $authRoleModules
                ->concat($employeeModules)
                ->groupBy('id')
                ->map(function ($moduleGroup) {
                    $first = $moduleGroup->first();

                    return [
                        'id' => $first['id'],
                        'module_name' => $first['module_name'],
                        'module_icon' => $first['module_icon'],
                        'module_identifier' => $first['module_identifier'],
                        'module_description' => $first['module_description'],
                        'module_is_active' => $first['module_is_active'],
                        'sources' => $moduleGroup->pluck('source')->unique()->values()->toArray(),
                        'auth_role_ids' => $moduleGroup->pluck('auth_role_ids')->flatten()->unique()->values()->toArray(),
                        'accessible_roles' => $moduleGroup->pluck('accessible_roles')->flatten()->unique()->values()->toArray(),
                        'role_ids' => $moduleGroup->pluck('role_ids')->flatten()->unique()->values()->toArray(),
                    ];
                })
                ->sortBy('module_name')
                ->values();

            if ($groupedModules->isEmpty()) {
                return $this->sendResponse([
                    'modules' => [],
                    'total_modules' => 0,
                    'user_id' => $user->id,
                    'national_id' => $nationalId,
                    'auth_role_ids' => $authRoleIds,
                    'employee_role_ids' => $employeeRoleIds,
                ], 'No accessible modules found for this user.');
            }

            Log::info('User accessible modules retrieved', [
                'user_id' => $user->id,
                'national_id' => $nationalId,
                'auth_role_ids' => $authRoleIds,
                'employee_role_ids' => $employeeRoleIds,
                'modules_count' => $groupedModules->count(),
            ]);

            return $this->sendResponse([
                'user_id' => $user->id,
                'national_id' => $nationalId,
                'auth_role_ids' => $authRoleIds,
                'employee_role_ids' => $employeeRoleIds,
                'modules' => $groupedModules,
                'total_modules' => $groupedModules->count(),
            ], 'User accessible modules retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching user accessible modules: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve user accessible modules: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

