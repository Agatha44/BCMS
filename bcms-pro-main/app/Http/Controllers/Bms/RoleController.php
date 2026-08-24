<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchRoles(Request $request)
    {
        try {
            $search = $request->search;

            $query = DB::table('bcmis2.roles as r')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'r.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'r.modified_by')
                ->select(
                    'r.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->orderBy('r.id', 'asc');

            if ($search) {
                $query->where('r.role_name', 'like', "%{$search}%");
            }

            // Order by id Ascending
            $role = $query->orderBy('r.id', 'asc')->get();

            return response()->json([
                'success' => true,
                'data'    => $role,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerRole(Request $request)
    {
        // Check if role_name already exists using the model (which uses bcmis2 connection)
        $existingRole = Role::where('role_name', $request->role_name)->first();
        if ($existingRole) {
            return response()->json([
                'success' => false,
                'errors'  => ['role_name' => ['The role name has already been taken.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'role_name'        => 'required|string|max:100',
            'role_description' => 'required|string',
            'is_active'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $role = Role::create([
            'role_name'        => $request->role_name,
            'role_description' => $request->role_description,
            'is_active'        => $request->is_active,
            'created_by'       => auth()->id(),
            'created_at'       => now(),
            'modified_by'      => auth()->id(),
            'modified_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data'    => $role,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bms\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function showRoles(Role $role)
    {
        $roleData = DB::table('bcmis2.roles as r')
            ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'r.created_by')
            ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'r.modified_by')
            ->where('r.id', $role->id)
            ->select(
                'r.id',
                'r.role_name',
                'r.role_description',
                'r.is_active',
                'r.created_at',
                'r.modified_at',
                DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
            )
            ->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => $roleData->id,
                'role_name'       => $roleData->role_name,
                'role_description'=> $roleData->role_description,
                'is_active'       => $roleData->is_active,
                'created_by'      => $roleData->created_by,
                'created_at'      => $roleData->created_at,
                'modified_by'     => $roleData->modified_by,
                'modified_at'     => $roleData->modified_at,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bms\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRole(Request $request, Role $role)
    {
        // Check if role_name already exists (excluding current role) using the model (which uses bcmis2 connection)
        $existingRole = Role::where('role_name', $request->role_name)
            ->where('id', '!=', $role->id)
            ->first();
        if ($existingRole) {
            return response()->json([
                'success' => false,
                'errors'  => ['role_name' => ['The role name has already been taken.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'role_name'        => 'required|string|max:100',
            'role_description' => 'nullable|string',
            'is_active'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $role->update([
            'role_name'        => $request->role_name,
            'role_description' => $request->role_description,
            'is_active'        => $request->is_active,
            'modified_by'      => auth()->id(),
            'modified_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role updated',
            'data'    => $role,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Bms\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyRole(Role $role)
    {
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted',
        ]);
    }

    // Toggle role status
    public function toggleRoleStatus(Role $role)
    {
        $role->is_active   = !$role->is_active;
        $role->modified_by = auth()->id();
        $role->modified_at = now();
        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Role status updated',
            'data'    => $role,
        ]);
    }

    /**
     * Get active roles only
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveRoles()
    {
        try {
            $roles = Role::where('is_active', true)
                ->orderBy('role_name', 'asc')
                ->get(['id', 'role_name', 'role_description', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active roles retrieved successfully',
                'data' => $roles
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active roles: ' . $e->getMessage()
            ], 500);
        }
    }

}
