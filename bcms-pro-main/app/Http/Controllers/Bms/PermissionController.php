<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchPermissions(Request $request)
    {
        $search = $request->search;

        $permissions = DB::table('bcmis2.permissions as p')
            ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'p.created_by')
            ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'p.modified_by')
            ->select(
                'p.*',
                DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
            )
            ->orderBy('p.id', 'asc')
            ->get();

        if ($search) {
            $permissions->where('p.name', 'like', "%{$search}%");
        }

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerPermission(Request $request)
    {
        // Check if name already exists using the model (which uses bcmis2 connection)
        $existingPermission = Permission::where('name', $request->name)->first();
        if ($existingPermission) {
            return response()->json([
                'success' => false,
                'errors'  => ['name' => ['The permission name has already been taken.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:100',
            'controller'  => 'required|string|max:150',
            'permission'  => 'required|string|max:100',
            'route'       => 'required|string|max:255',
            'is_active'   => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $permission = Permission::create([
            'name'        => $request->name,
            'controller'  => $request->controller,
            'permission'  => $request->permission,
            'route'       => $request->route,
            'is_active'   => $request->is_active,
            'created_by'  => auth()->id(),
            'created_at'  => now(),
            'modified_by' => auth()->id(),
            'modified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data'    => $permission
        ], 201);
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Bms\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function showPermissions(Permission $permission)
    {
        $permission = DB::table('bcmis2.permissions as p')
            ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'p.created_by')
            ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'p.modified_by')
            ->select(
                'p.id',
                'p.controller',
                'p.permission',
                'p.name',
                'p.route',
                'p.is_active',
                'p.created_at',
                DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                'modified_at',
                DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
            )
            ->where('p.id', $permission->id)
            ->first();
        return response()->json([
            'success' => true,
            'data' => [
                'id'          => $permission->id,
                'name'        => $permission->name,
                'controller'  => $permission->controller,
                'permission'  => $permission->permission,
                'route'       => $permission->route,
                'is_active'   => $permission->is_active,
                'created_by'  => $permission->created_by,
                'created_at'  => $permission->created_at,
                'modified_by' => $permission->modified_by,
                'modified_at' => $permission->modified_at,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bms\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePermission(Request $request, Permission $permission)
    {
        // Check if name already exists (excluding current permission) using the model (which uses bcmis2 connection)
        $existingPermission = Permission::where('name', $request->name)
            ->where('id', '!=', $permission->id)
            ->first();
        if ($existingPermission) {
            return response()->json([
                'success' => false,
                'errors'  => ['name' => ['The permission name has already been taken.']],
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100',
            'controller' => 'required|string|max:100',
            'permission' => 'required|string|max:100',
            'route'      => 'required|string|max:150',
            'is_active'  => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $permission->update([
            'name'        => $request->name,
            'controller'  => $request->controller,
            'permission'  => $request->permission,
            'route'       => $request->route,
            'is_active'   => $request->is_active,
            'modified_by' => auth()->id(),
            'modified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data'    => $permission
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Bms\Permission  $permission
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyPermission(Permission $permission)
    {
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }

    public function togglePermissionStatus(Permission $permission)
    {
        $permission->is_active = !$permission->is_active;
        $permission->modified_by = auth()->id();
        $permission->modified_at = now();
        $permission->save();

        return response()->json([
            'success' => true,
            'message' => 'Permission status updated',
            'data'    => $permission
        ]);
    }
}
