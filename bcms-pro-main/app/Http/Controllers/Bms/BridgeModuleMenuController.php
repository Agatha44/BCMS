<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BridgeModuleMenuController extends BasicController
{
    /**
     * Display a listing of bridge module menus.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search');
            $moduleId = $request->input('module_id');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $isActive = $request->input('is_active');

            $query = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bridge_module_menu.menu_name', 'like', "%{$search}%")
                      ->orWhere('bridge_module.title', 'like', "%{$search}%");
                });
            }

            // Apply module filter
            if ($moduleId) {
                $query->where('bridge_module_menu.module_id', $moduleId);
            }

            // Apply active filter
            if ($isActive !== null) {
                $query->where('bridge_module_menu.is_active', $isActive);
            }

            // Apply sorting
            $query->orderBy('bridge_module_menu.' . $sortBy, $sortOrder);

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->input('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $menus = $query->offset($offset)->limit($perPage)->get();

            return $this->sendResponse([
                'menus' => $menus,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 'Bridge module menus retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge module menus: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve bridge module menus: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Store a newly created bridge module menu.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'module_id' => 'required|integer|exists:bcmis2.bridge_module,id',
                'menu_name' => 'required|string|max:255',
                'menu_path' => 'required|string|max:500',
                'menu_icon' => 'nullable|string|max:255',
                'menu_order' => 'nullable|integer',
                'menu_description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if module exists
            $module = DB::connection('bcmis2')->table('bridge_module')
                ->where('id', $request->module_id)
                ->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            $menuData = [
                'module_id' => $request->module_id,
                'menu_name' => $request->menu_name,
                'menu_path' => $request->menu_path,
                'menu_icon' => $request->menu_icon ?? null,
                'menu_order' => $request->menu_order ?? null,
                'menu_description' => $request->menu_description ?? null,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            $id = DB::connection('bcmis2')->table('bridge_module_menu')->insertGetId($menuData);

            $menu = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_menu.id', $id)
                ->first();

            Log::info('Bridge module menu created successfully', [
                'menu_id' => $id,
                'module_id' => $request->module_id,
                'menu_name' => $request->menu_name,
                'created_by' => auth()->id()
            ]);

            return $this->sendResponse($menu, 'Bridge module menu created successfully');

        } catch (\Exception $e) {
            Log::error('Error creating bridge module menu: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create bridge module menu: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified bridge module menu.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $menu = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_menu.id', $id)
                ->first();

            if (!$menu) {
                return $this->sendError('Bridge module menu not found', [], 0, 404);
            }

            return $this->sendResponse($menu, 'Bridge module menu retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge module menu: ' . $e->getMessage(), [
                'menu_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve bridge module menu: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update the specified bridge module menu.
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
                'menu_name' => 'nullable|string|max:255',
                'menu_path' => 'nullable|string|max:500',
                'menu_icon' => 'nullable|string|max:255',
                'menu_order' => 'nullable|integer',
                'menu_description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'modified_by' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if menu exists
            $menu = DB::connection('bcmis2')->table('bridge_module_menu')->where('id', $id)->first();

            if (!$menu) {
                return $this->sendError('Bridge module menu not found', [], 0, 404);
            }

            // Check if module exists if module_id is being updated
            if ($request->has('module_id')) {
                $module = DB::connection('bcmis2')->table('bridge_module')
                    ->where('id', $request->module_id)
                    ->first();

                if (!$module) {
                    return $this->sendError('Bridge module not found', [], 0, 404);
                }
            }

            $updateData = [
                'modified_by' => $request->modified_by,
                'modified_at' => now(),
            ];

            if ($request->has('module_id')) {
                $updateData['module_id'] = $request->module_id;
            }

            if ($request->has('menu_name')) {
                $updateData['menu_name'] = $request->menu_name;
            }

            if ($request->has('menu_path')) {
                $updateData['menu_path'] = $request->menu_path;
            }

            if ($request->has('menu_icon')) {
                $updateData['menu_icon'] = $request->menu_icon;
            }

            if ($request->has('menu_order')) {
                $updateData['menu_order'] = $request->menu_order;
            }

            if ($request->has('menu_description')) {
                $updateData['menu_description'] = $request->menu_description;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = (bool) $request->is_active;
            }

            DB::connection('bcmis2')->table('bridge_module_menu')
                ->where('id', $id)
                ->update($updateData);

            $updatedMenu = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_menu.id', $id)
                ->first();

            Log::info('Bridge module menu updated successfully', [
                'menu_id' => $id,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedMenu, 'Bridge module menu updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating bridge module menu: ' . $e->getMessage(), [
                'menu_id' => $id,
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to update bridge module menu: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove the specified bridge module menu.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $menu = DB::connection('bcmis2')->table('bridge_module_menu')->where('id', $id)->first();

            if (!$menu) {
                return $this->sendError('Bridge module menu not found', [], 0, 404);
            }

            DB::connection('bcmis2')->table('bridge_module_menu')->where('id', $id)->delete();

            Log::info('Bridge module menu deleted successfully', [
                'menu_id' => $id
            ]);

            return $this->sendResponse(null, 'Bridge module menu deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting bridge module menu: ' . $e->getMessage(), [
                'menu_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to delete bridge module menu: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle the active status of a bridge module menu.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            $menu = DB::connection('bcmis2')->table('bridge_module_menu')->where('id', $id)->first();

            if (!$menu) {
                return $this->sendError('Bridge module menu not found', [], 0, 404);
            }

            $newStatus = !$menu->is_active;

            // currently authenticated user ID.
            $modifierId = auth()->id();

            DB::connection('bcmis2')->table('bridge_module_menu')
                ->where('id', $id)
                ->update([
                    'is_active' => $newStatus,
                    'modified_by' => $modifierId,
                    'modified_at' => now(),
                ]);

            $updatedMenu = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_menu.id', $id)
                ->first();

            Log::info('Bridge module menu status toggled', [
                'menu_id' => $id,
                'new_status' => $newStatus,
                'modified_by' => $request->modified_by
            ]);

            return $this->sendResponse($updatedMenu, 'Bridge module menu status updated successfully');

        } catch (\Exception $e) {
            Log::error('Error toggling bridge module menu status: ' . $e->getMessage(), [
                'menu_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to toggle bridge module menu status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get menus by module ID.
     *
     * @param int $moduleId
     * @return JsonResponse
     */
    public function getByModule($moduleId): JsonResponse
    {
        try {
            // Check if module exists
            $module = DB::connection('bcmis2')->table('bridge_module')->where('id', $moduleId)->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            $menus = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->where('module_id', $moduleId)
                ->where('is_active', true)
                ->orderBy('menu_name', 'asc')
                ->get();

            return $this->sendResponse([
                'module' => $module,
                'menus' => $menus
            ], 'Bridge module menus retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching menus by module: ' . $e->getMessage(), [
                'module_id' => $moduleId,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve bridge module menus: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get active bridge module menus.
     *
     * @return JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $menus = DB::connection('bcmis2')
                ->table('bridge_module_menu')
                ->leftJoin('bridge_module', 'bridge_module_menu.module_id', '=', 'bridge_module.id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module_menu.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module_menu.modified_by')
                ->select(
                    'bridge_module_menu.*',
                    'bridge_module.title',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module_menu.is_active', true)
                ->where('bridge_module.is_active', true)
                ->orderBy('bridge_module.title', 'asc')
                ->orderBy('bridge_module_menu.menu_name', 'asc')
                ->get();

            return $this->sendResponse($menus, 'Active bridge module menus retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active bridge module menus: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve active bridge module menus: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

