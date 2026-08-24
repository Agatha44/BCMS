<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuthAction;
use App\Models\AuthRole;
use App\Models\AuthRoleAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthActionController extends Controller
{
    /**
     * Get all actions with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AuthAction::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('controller_id', 'like', "%{$search}%")
                      ->orWhere('action_id', 'like', "%{$search}%")
                      ->orWhere('route', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== null) {
                $query->where('is_active', $request->status);
            }

            // Menu filter
            if ($request->has('on_menu') && $request->on_menu !== null) {
                $query->where('on_menu', $request->on_menu);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'order_no');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $actions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Actions retrieved successfully',
                'data' => [
                    'actions' => $actions->items(),
                    'pagination' => [
                        'current_page' => $actions->currentPage(),
                        'last_page' => $actions->lastPage(),
                        'per_page' => $actions->perPage(),
                        'total' => $actions->total(),
                        'from' => $actions->firstItem(),
                        'to' => $actions->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve actions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific action
     */
    public function show($id): JsonResponse
    {
        try {
            $action = AuthAction::find($id);

            if (!$action) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Action retrieved successfully',
                'data' => $action
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new action
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:50',
                'controller_id' => 'required|string|max:50',
                'action_id' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:50',
                'menu_icon' => 'nullable|string|max:50',
                'on_menu' => 'boolean',
                'order_no' => 'integer',
                'is_active' => 'boolean',
                'parent_id' => 'nullable|integer|exists:auth_action,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $action = AuthAction::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Action created successfully',
                'data' => $action
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an action
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $action = AuthAction::find($id);

            if (!$action) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:50',
                'controller_id' => 'required|string|max:50',
                'action_id' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:50',
                'menu_icon' => 'nullable|string|max:50',
                'on_menu' => 'boolean',
                'order_no' => 'integer',
                'is_active' => 'boolean',
                'parent_id' => 'nullable|integer|exists:auth_action,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $action->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Action updated successfully',
                'data' => $action
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update action: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle action status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $action = AuthAction::find($id);

            if (!$action) {
                return response()->json([
                    'success' => false,
                    'message' => 'Action not found'
                ], 404);
            }

            $action->is_active = !$action->is_active;
            $action->save();

            return response()->json([
                'success' => true,
                'message' => 'Action status updated successfully',
                'data' => $action
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update action status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available actions for role assignment
     */
    public function getAvailableActions(): JsonResponse
    {
        try {
            $actions = AuthAction::active()
                ->orderBy('order_no')
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Available actions retrieved successfully',
                'data' => $actions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve available actions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get actions assigned to a specific role
     */
    public function getRoleActions($roleId): JsonResponse
    {
        try {
            $role = AuthRole::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $actions = $role->actions()
                ->wherePivot('is_active', 1)
                ->orderBy('order_no')
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Role actions retrieved successfully',
                'data' => $actions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role actions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign actions to a role
     */
    public function assignRoleActions(Request $request, $roleId): JsonResponse
    {
        try {
            $role = AuthRole::find($roleId);

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'action_ids' => 'required|array',
                'action_ids.*' => 'integer|exists:auth_action,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Remove existing role actions
            AuthRoleAction::where('role_id', $roleId)->delete();

            // Add new role actions
            $roleActions = [];
            foreach ($request->action_ids as $actionId) {
                $roleActions[] = [
                    'role_id' => $roleId,
                    'action_id' => $actionId,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            AuthRoleAction::insert($roleActions);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Actions assigned to role successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign actions to role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get menu items for a user based on their roles
     */
    public function getUserMenuItems(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get user's roles
            $userRoles = $user->roles()->pluck('auth_role.id');

            if ($userRoles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No menu items available',
                    'data' => []
                ]);
            }

            // Get actions accessible to user's roles
            $menuItems = AuthAction::where('on_menu', 1)
                ->where('is_active', 1)
                ->whereHas('roles', function ($query) use ($userRoles) {
                    $query->whereIn('auth_role.id', $userRoles)
                          ->wherePivot('is_active', 1);
                })
                ->orderBy('order_no')
                ->orderBy('title')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Menu items retrieved successfully',
                'data' => $menuItems
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu items: ' . $e->getMessage()
            ], 500);
        }
    }
}
