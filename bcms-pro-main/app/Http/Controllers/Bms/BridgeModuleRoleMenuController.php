<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BridgeModuleRoleMenuController extends BasicController
{
    /**
     * Display a listing of role-menu assignments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $roleId = $request->input('role_id');
            $menuId = $request->input('menu_id');
            $isActive = $request->input('is_active');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Apply role filter
            if ($roleId) {
                $query->where('bridge_module_role_menu.role_id', $roleId);
            }

            // Apply menu filter
            if ($menuId) {
                $query->where('bridge_module_role_menu.menu_id', $menuId);
            }

            // Apply active filter
            if ($isActive !== null) {
                $query->where('bridge_module_role_menu.is_active', $isActive);
            }

            // Apply sorting
            $query->orderBy('bridge_module_role_menu.' . $sortBy, $sortOrder);

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
            ], 'Role-menu assignments retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching role-menu assignments: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve role-menu assignments: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all active role-menu assignments.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getActive(Request $request): JsonResponse
    {
        try {
            $roleId = $request->input('role_id');
            $menuId = $request->input('menu_id');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role_menu.is_active', true)
                ->where('roles.is_active', true)
                ->where('bridge_module_menu.is_active', true)
                ->where('bridge_module.is_active', true);

            // Apply role filter
            if ($roleId) {
                $query->where('bridge_module_role_menu.role_id', $roleId);
            }

            // Apply menu filter
            if ($menuId) {
                $query->where('bridge_module_role_menu.menu_id', $menuId);
            }

            // Apply sorting
            $query->orderBy('bridge_module_role_menu.' . $sortBy, $sortOrder);

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
            ], 'Active role-menu assignments retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active role-menu assignments: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve active role-menu assignments: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Store a newly created role-menu assignment.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'menu_id' => 'required|integer|exists:bcmis2.bridge_module_menu,id',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if assignment already exists
            $existing = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->where('role_id', $request->role_id)
                ->where('menu_id', $request->menu_id)
                ->first();

            if ($existing) {
                return $this->sendError('This role-menu assignment already exists', [
                    'assignment_id' => $existing->id,
                    'role_id' => $request->role_id,
                    'menu_id' => $request->menu_id
                ], 0, 409);
            }

            // Verify role exists
            $role = DB::connection('bcmis2')->table('roles')->where('id', $request->role_id)->first();
            if (!$role) {
                return $this->sendError('Role not found', [], 0, 404);
            }

            // Verify menu exists
            $menu = DB::connection('bcmis2')->table('bridge_module_menu')->where('id', $request->menu_id)->first();
            if (!$menu) {
                return $this->sendError('Menu not found', [], 0, 404);
            }

            $assignmentData = [
                'role_id' => $request->role_id,
                'menu_id' => $request->menu_id,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            $id = DB::connection('bcmis2')->table('bridge_module_role_menu')->insertGetId($assignmentData);

            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role_menu.id', $id)
                ->first();

            Log::info('Role-menu assignment created successfully', [
                'assignment_id' => $id,
                'role_id' => $request->role_id,
                'menu_id' => $request->menu_id,
                'created_by' => auth()->id()
            ]);

            return $this->sendResponse($assignment, 'Role-menu assignment created successfully');

        } catch (\Exception $e) {
            Log::error('Error creating role-menu assignment: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create role-menu assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified role-menu assignment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")    
                )
                ->where('bridge_module_role_menu.id', $id)
                ->first();

            if (!$assignment) {
                return $this->sendError('Role-menu assignment not found', [], 0, 404);
            }

            return $this->sendResponse($assignment, 'Role-menu assignment retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching role-menu assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve role-menu assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update the specified role-menu assignment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'nullable|integer|exists:bcmis2.roles,id',
                'menu_id' => 'nullable|integer|exists:bcmis2.bridge_module_menu,id',
                'is_active' => 'nullable|boolean',
                'modified_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if assignment exists
            $assignment = DB::connection('bcmis2')->table('bridge_module_role_menu')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Role-menu assignment not found', [], 0, 404);
            }

            // Check for duplicate if role_id or menu_id is being updated
            if ($request->has('role_id') || $request->has('menu_id')) {
                $newRoleId = $request->has('role_id') ? $request->role_id : $assignment->role_id;
                $newMenuId = $request->has('menu_id') ? $request->menu_id : $assignment->menu_id;

                $duplicate = DB::connection('bcmis2')
                    ->table('bridge_module_role_menu')
                    ->where('role_id', $newRoleId)
                    ->where('menu_id', $newMenuId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($duplicate) {
                    return $this->sendError('This role-menu assignment already exists', [
                        'existing_assignment_id' => $duplicate->id
                    ], 0, 409);
                }
            }

            $updateData = [
                'modified_by' => $request->modified_by,
                'modified_at' => now(),
            ];

            if ($request->has('role_id')) {
                $updateData['role_id'] = $request->role_id;
            }

            if ($request->has('menu_id')) {
                $updateData['menu_id'] = $request->menu_id;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = (bool) $request->is_active;
            }

            DB::connection('bcmis2')->table('bridge_module_role_menu')
                ->where('id', $id)
                ->update($updateData);

            $updatedAssignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role_menu.id', $id)
                ->first();

            Log::info('Role-menu assignment updated successfully', [
                'assignment_id' => $id,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedAssignment, 'Role-menu assignment updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating role-menu assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to update role-menu assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove the specified role-menu assignment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $assignment = DB::connection('bcmis2')->table('bridge_module_role_menu')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Role-menu assignment not found', [], 0, 404);
            }

            DB::connection('bcmis2')->table('bridge_module_role_menu')->where('id', $id)->delete();

            Log::info('Role-menu assignment deleted successfully', [
                'assignment_id' => $id
            ]);

            return $this->sendResponse(null, 'Role-menu assignment deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting role-menu assignment: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to delete role-menu assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Assign a menu to a role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignMenuToRole(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'menu_id' => 'required|integer|exists:bcmis2.bridge_module_menu,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if assignment already exists
            $existing = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->where('role_id', $request->role_id)
                ->where('menu_id', $request->menu_id)
                ->first();

            if ($existing) {
                // If exists but inactive, activate it
                if (!$existing->is_active) {
                    DB::connection('bcmis2')
                        ->table('bridge_module_role_menu')
                        ->where('id', $existing->id)
                        ->update([
                            'is_active' => true,
                            'modified_by' => auth()->id(),
                            'modified_at' => now(),
                        ]);

                    $assignment = DB::connection('bcmis2')
                        ->table('bridge_module_role_menu')
                        ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                        ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                        ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                        ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                        ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                        ->select(
                            'bridge_module_role_menu.*',
                            'roles.role_name',
                            'roles.role_description',
                            'bridge_module_menu.menu_name',
                            'bridge_module.id as module_id',
                            'bridge_module.title as module_name',
                            DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                            DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                        )
                        ->where('bridge_module_role_menu.id', $existing->id)
                        ->first();

                    return $this->sendResponse($assignment, 'Menu access activated for role');
                }

                return $this->sendError('Menu is already assigned to this role', [
                    'assignment_id' => $existing->id
                ], 0, 409);
            }

            // Create new assignment
            $assignmentData = [
                'role_id' => $request->role_id,
                'menu_id' => $request->menu_id,
                'is_active' => true,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            $id = DB::connection('bcmis2')->table('bridge_module_role_menu')->insertGetId($assignmentData);

            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role_menu.id', $id)
                ->first();

            Log::info('Menu assigned to role successfully', [
                'assignment_id' => $id,
                'role_id' => $request->role_id,
                'menu_id' => $request->menu_id
            ]);

            return $this->sendResponse($assignment, 'Menu assigned to role successfully');

        } catch (\Exception $e) {
            Log::error('Error assigning menu to role: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to assign menu to role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Revoke a menu from a role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function revokeMenuFromRole(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'menu_id' => 'required|integer|exists:bcmis2.bridge_module_menu,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $assignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->where('role_id', $request->role_id)
                ->where('menu_id', $request->menu_id)
                ->first();

            if (!$assignment) {
                return $this->sendError('Menu is not assigned to this role', [], 0, 404);
            }

            // Delete the assignment
            DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->where('id', $assignment->id)
                ->delete();

            Log::info('Menu revoked from role successfully', [
                'assignment_id' => $assignment->id,
                'role_id' => $request->role_id,
                'menu_id' => $request->menu_id
            ]);

            return $this->sendResponse(null, 'Menu revoked from role successfully');

        } catch (\Exception $e) {
            Log::error('Error revoking menu from role: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to revoke menu from role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all menus assigned to a role.
     *
     * @param int $roleId
     * @return JsonResponse
     */
    public function getMenusByRole($roleId): JsonResponse
    {
        try {
            // Verify role exists
            $role = DB::connection('bcmis2')->table('roles')->where('id', $roleId)->first();
            if (!$role) {
                return $this->sendError('Role not found', [], 0, 404);
            }

            $menus = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->select(
                    'bridge_module_role_menu.*',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    'bridge_module_menu.is_active as menu_is_active',
                    'bridge_module.is_active as module_is_active'
                )
                ->where('bridge_module_role_menu.role_id', $roleId)
                ->where('bridge_module_role_menu.is_active', true)
                ->orderBy('bridge_module.title', 'asc')
                ->orderBy('bridge_module_menu.menu_name', 'asc')
                ->get();

            return $this->sendResponse([
                'role' => $role,
                'menus' => $menus,
                'total_menus' => $menus->count()
            ], 'Menus for role retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching menus by role: ' . $e->getMessage(), [
                'role_id' => $roleId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve menus for role: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all roles that have access to a menu.
     *
     * @param int $menuId
     * @return JsonResponse
     */
    public function getRolesByMenu($menuId): JsonResponse
    {
        try {
            // Verify menu exists
            $menu = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->select('bridge_module_menu.*', 'bridge_module.id as module_id', 'bridge_module.title as module_name')
                ->where('bridge_module_menu.id', $menuId)
                ->first();

            if (!$menu) {
                return $this->sendError('Menu not found', [], 0, 404);
            }

            $roles = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'roles.is_active as role_is_active'
                )
                ->where('bridge_module_role_menu.menu_id', $menuId)
                ->where('bridge_module_role_menu.is_active', true)
                ->orderBy('roles.role_name', 'asc')
                ->get();

            return $this->sendResponse([
                'menu' => $menu,
                'roles' => $roles,
                'total_roles' => $roles->count()
            ], 'Roles with menu access retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching roles by menu: ' . $e->getMessage(), [
                'menu_id' => $menuId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve roles for menu: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle the active status of a role-menu assignment.
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

            $assignment = DB::connection('bcmis2')->table('bridge_module_role_menu')->where('id', $id)->first();

            if (!$assignment) {
                return $this->sendError('Role-menu assignment not found', [], 0, 404);
            }

            $newStatus = !$assignment->is_active;

            DB::connection('bcmis2')->table('bridge_module_role_menu')
                ->where('id', $id)
                ->update([
                    'is_active' => $newStatus,
                    'modified_by' => $request->modified_by,
                    'modified_at' => now(),
                ]);

            $updatedAssignment = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('roles', 'bridge_module_role_menu.role_id', '=', 'roles.id')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_role_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_role_menu.modified_by')
                ->select(
                    'bridge_module_role_menu.*',
                    'roles.role_name',
                    'roles.role_description',
                    'bridge_module_menu.menu_name',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_role_menu.id', $id)
                ->first();

            Log::info('Role-menu assignment status toggled', [
                'assignment_id' => $id,
                'new_status' => $newStatus,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedAssignment, 'Role-menu assignment status updated successfully');

        } catch (\Exception $e) {
            Log::error('Error toggling role-menu assignment status: ' . $e->getMessage(), [
                'assignment_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to toggle role-menu assignment status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get menus for the authenticated user based on their roles, optionally filtered by module.
     * This replaces frontend logic and ensures menus are filtered by the selected module.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserMenus(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return $this->sendError('User not authenticated', [], 0, 401);
            }

            // Get user's national_id from auth_user.nida field
            $nationalId = $user->nida ?? null;

            if (!$nationalId) {
                return $this->sendResponse([
                    'menus' => [],
                    'total_menus' => 0,
                    'module_id' => $request->input('module_id')
                ], 'User has no national_id (nida) associated. No menus accessible.');
            }

            // Get user's active roles from bridge_employee_role
            $userRoleIds = DB::connection('bcmis2')
                ->table('bridge_employee_role')
                ->where('national_id', $nationalId)
                ->where('is_active', true)
                ->pluck('role_id')
                ->toArray();

            if (empty($userRoleIds)) {
                return $this->sendResponse([
                    'menus' => [],
                    'total_menus' => 0,
                    'user_national_id' => $nationalId,
                    'module_id' => $request->input('module_id')
                ], 'User has no active roles assigned. No menus accessible.');
            }

            // Get optional module_id filter
            $moduleId = $request->input('module_id');

            // Build query for menus accessible to these roles
            $query = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->leftJoin('bridge_module_menu', 'bridge_module_role_menu.menu_id', '=', 'bridge_module_menu.id')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->select(
                    'bridge_module_menu.id',
                    'bridge_module_menu.menu_name',
                    'bridge_module_menu.menu_icon',
                    'bridge_module_menu.route',
                    'bridge_module_menu.parent_menu_id',
                    'bridge_module_menu.order_no',
                    'bridge_module.id as module_id',
                    'bridge_module.title as module_name',
                    'bridge_module.icon as module_icon'
                )
                ->whereIn('bridge_module_role_menu.role_id', $userRoleIds)
                ->where('bridge_module_role_menu.is_active', true)
                ->where('bridge_module_menu.is_active', true)
                ->where('bridge_module.is_active', true);

            // Filter by module_id if provided
            if ($moduleId) {
                $query->where('bridge_module.id', $moduleId);
            }

            $menus = $query
                ->distinct()
                ->orderBy('bridge_module_menu.order_no', 'asc')
                ->orderBy('bridge_module_menu.menu_name', 'asc')
                ->get();

            Log::info('User menus retrieved', [
                'user_id' => $user->id,
                'national_id' => $nationalId,
                'role_ids' => $userRoleIds,
                'module_id' => $moduleId,
                'menus_count' => $menus->count()
            ]);

            return $this->sendResponse([
                'user_id' => $user->id,
                'national_id' => $nationalId,
                'user_role_ids' => $userRoleIds,
                'module_id' => $moduleId,
                'menus' => $menus,
                'total_menus' => $menus->count()
            ], $moduleId 
                ? 'User menus for selected module retrieved successfully' 
                : 'User menus retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching user menus: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve user menus: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Bulk assign menus to a role.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkAssignMenus(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'role_id' => 'required|integer|exists:bcmis2.roles,id',
                'menu_id' => 'required|array',
                'menu_id.*' => 'integer|exists:bcmis2.bridge_module_menu,id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $roleId = $request->role_id;
            $menuIds = array_unique($request->menu_id);
            $createdBy = auth()->id();

            // Get existing assignments
            $existing = DB::connection('bcmis2')
                ->table('bridge_module_role_menu')
                ->where('role_id', $roleId)
                ->whereIn('menu_id', $menuIds)
                ->pluck('menu_id')
                ->toArray();

            $newMenuIds = array_diff($menuIds, $existing);
            $alreadyAssigned = count($existing);
            $newlyAssigned = 0;

            if (!empty($newMenuIds)) {
                $insertData = [];
                foreach ($newMenuIds as $menuId) {
                    $insertData[] = [
                        'role_id' => $roleId,
                        'menu_id' => $menuId,
                        'is_active' => true,
                        'created_by' => $createdBy,
                        'created_at' => now(),
                    ];
                }

                DB::connection('bcmis2')->table('bridge_module_role_menu')->insert($insertData);
                $newlyAssigned = count($insertData);
            }

            Log::info('Bulk menu assignment completed', [
                'role_id' => $roleId,
                'total_requested' => count($menuIds),
                'already_assigned' => $alreadyAssigned,
                'newly_assigned' => $newlyAssigned
            ]);

            return $this->sendResponse([
                'role_id' => $roleId,
                'total_requested' => count($menuIds),
                'already_assigned' => $alreadyAssigned,
                'newly_assigned' => $newlyAssigned,
                'skipped' => $alreadyAssigned
            ], 'Bulk menu assignment completed successfully');

        } catch (\Exception $e) {
            Log::error('Error in bulk menu assignment: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to bulk assign menus: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

