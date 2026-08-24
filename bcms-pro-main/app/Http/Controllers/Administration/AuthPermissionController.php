<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuthPermission;
use App\Models\AuthRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthPermissionController extends Controller
{
    /**
     * Get all permissions with pagination and filtering
     */
    public function index(Request $request)
    {
        try {
            $query = AuthPermission::query();

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status !== '') {
                $query->where('is_active', $request->status);
            }

            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $permissions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'permissions' => $permissions->items(),
                    'pagination' => [
                        'current_page' => $permissions->currentPage(),
                        'last_page' => $permissions->lastPage(),
                        'per_page' => $permissions->perPage(),
                        'total' => $permissions->total(),
                        'from' => $permissions->firstItem(),
                        'to' => $permissions->lastItem()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific permission
     */
    public function show($id)
    {
        try {
            $permission = AuthPermission::find($id);
            
            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $permission
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new permission
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50|unique:auth_permission,name',
                'description' => 'required|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $permission = AuthPermission::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', 1),
                'created_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a permission
     */
    public function update(Request $request, $id)
    {
        try {
            $permission = AuthPermission::find($id);
            
            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50|unique:auth_permission,name,' . $id,
                'description' => 'required|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $permission->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', $permission->is_active),
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle permission status
     */
    public function toggleStatus($id)
    {
        try {
            $permission = AuthPermission::find($id);
            
            if (!$permission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission not found'
                ], 404);
            }

            $permission->update([
                'is_active' => !$permission->is_active,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission status updated successfully',
                'data' => $permission
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permission status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permissions for a specific role
     */
    public function getRolePermissions($roleId)
    {
        try {
            $role = AuthRole::find($roleId);
            
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], 404);
            }

            $permissions = DB::table('auth_permission')
                ->join('auth_role_permission', 'auth_role_permission.permission_id', '=', 'auth_permission.id')
                ->where('auth_role_permission.role_id', $roleId)
                ->select('auth_role_permission.id', 'auth_permission.name', 'auth_permission.description')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch role permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign permissions to a role
     */
    public function assignPermissions(Request $request, $roleId)
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
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'integer|exists:auth_permission,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Remove existing permissions
            DB::table('auth_role_permission')->where('role_id', $roleId)->delete();

            // Add new permissions
            $permissions = [];
            foreach ($request->permission_ids as $permissionId) {
                $permissions[] = [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId
                ];
            }

            if (!empty($permissions)) {
                DB::table('auth_role_permission')->insert($permissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permissions assigned successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available permissions (for assignment)
     */
    public function getAvailablePermissions()
    {
        try {
            $permissions = AuthPermission::active()->get();

            return response()->json([
                'success' => true,
                'data' => $permissions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available permissions: ' . $e->getMessage()
            ], 500);
        }
    }
}
