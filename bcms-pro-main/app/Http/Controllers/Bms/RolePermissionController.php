<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchRolePermissions(Request $request)
    {
        $roleId = $request->role_id;

        $rolePermissions = DB::table('bcmis2.role_permissions as r')
            ->leftJoin('bcmis2.roles', 'roles.id', '=', 'r.role_id')
            ->leftJoin('bcmis2.permissions', 'permissions.id', '=', 'r.permission_id')
            ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'r.created_by')
            ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'r.modified_by')
            ->select(
                'r.*',
                'roles.role_name as role_name',
                'permissions.name as permission_name',
                'permissions.permission',
                DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
            )
            ->where('r.role_id', $roleId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rolePermissions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignPermissionRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id'       => 'required|exists:bcmis2.roles,id',
            'permission_id' => 'required|exists:bcmis2.permissions,id',
            'is_active'     => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

            // Prevent duplicate role-permission mapping
            $exists = RolePermission::where('role_id', $request->role_id)
                ->where('permission_id', $request->permission_id)
                ->get();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission already assigned to this role'
                ], 409);
            }

            $rolePermission = RolePermission::create([
                'role_id'       => $request->role_id,
                'permission_id' => $request->permission_id,
                'is_active'     => $request->is_active,
                'created_by'    => auth()->id(),
                'created_at'    => now(),
                'modified_by'   => auth()->id(),
                'modified_at'   => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission assigned to role successfully',
            'data'    => $rolePermission
        ], 201);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bms\RolePermission  $rolePermission
     * @return \Illuminate\Http\JsonResponse
     */
    public function showRolePermissions(RolePermission $rolePermission)
    {

        $rolePermissionData = DB::table('bcmis2.role_permissions as rp')
            ->leftJoin('bcmis2.permissions as p', 'p.id', '=', 'rp.permission_id')
            ->leftJoin('bcmis2.roles as r', 'r.id', '=', 'rp.role_id')
            ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'rp.created_by')
            ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'rp.modified_by')
            ->select(
                'rp.id',
                'r.role_name as role_name',
                'p.name as permission_name',
                'rp.is_active',
                'rp.created_at',
                'rp.modified_at',
                DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
            )
            ->where('rp.id', $rolePermission->id)
            ->first();

            if (!$rolePermissionData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role permission not found',
                ], 404);
            }

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $rolePermissionData->id,
                'role_name'      => $rolePermissionData->role_name,
                'permission_name'=> $rolePermissionData->permission_name,
                'is_active'      => $rolePermissionData->is_active,
                'created_by'     => $rolePermissionData->created_by_name,
                'created_at'     => $rolePermissionData->created_at,
                'modified_by'    => $rolePermissionData->modified_by_name,
                'modified_at'    => $rolePermissionData->modified_at,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bms\RolePermission  $rolePermission
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRolePermission(Request $request, RolePermission $rolePermission)
    {
        $validator = Validator::make($request->all(), [
            'role_id'       => 'sometimes|exists:bcmis2.roles,id',
            'permission_id' => 'sometimes|exists:bcmis2.permissions,id',
            'is_active'     => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // If role_id or permission_id are changing, prevent duplicate mapping
        $newRoleId       = $request->has('role_id') ? $request->role_id : $rolePermission->role_id;
        $newPermissionId = $request->has('permission_id') ? $request->permission_id : $rolePermission->permission_id;

        $exists = RolePermission::where('role_id', $newRoleId)
            ->where('permission_id', $newPermissionId)
            ->where('id', '!=', $rolePermission->id)
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already assigned to this role',
            ], 409);
        }

        $rolePermission->update([
            'role_id'       => $newRoleId,
            'permission_id' => $newPermissionId,
            'is_active'     => $request->is_active,
            'modified_by'   => auth()->id(),
            'modified_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role permission updated successfully',
            'data'    => $rolePermission
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Bms\RolePermission  $rolePermission
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyRolePermission(RolePermission $rolePermission)
    {
        $rolePermission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission removed from role successfully'
        ]);
    }


    public function toggleRolePermissionStatus(RolePermission $rolePermission)
    {
        $rolePermission->is_active = !$rolePermission->is_active;
        $rolePermission->modified_by = auth()->id();
        $rolePermission->modified_at = now();
        $rolePermission->save();

        return response()->json([
            'success' => true,
            'message' => 'Role permission status updated',
            'data'    => $rolePermission
        ]);
    }
}
