<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BridgeModuleController extends BasicController
{
    /**
     * Display a listing of bridge modules.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');
            $isActive = $request->input('is_active');

            $query = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Apply search filter
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('bridge_module.title', 'like', "%{$search}%")
                      ->orWhere('bridge_module.module_id', 'like', "%{$search}%");
                });
            }

            // Apply active filter
            if ($isActive !== null) {
                $query->where('bridge_module.is_active', $isActive);
            }

            // Apply sorting
            $query->orderBy('bridge_module.' . $sortBy, $sortOrder);

            // Get total count before pagination
            $total = $query->count();

            // Apply pagination
            $page = $request->input('page', 1);
            $perPage = (int) $perPage;
            $offset = ($page - 1) * $perPage;
            $modules = $query->offset($offset)->limit($perPage)->get();

            return $this->sendResponse([
                'modules' => $modules,
                'pagination' => [
                    'current_page' => (int) $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 'Bridge modules retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge modules: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve bridge modules: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Store a newly created bridge module.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255', // Title is required
                'icon' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'module_id' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if module_id is unique (manual check for cross-database connection)
            if ($request->has('module_id') && $request->module_id) {
                $existingModule = DB::connection('bcmis2')->table('bridge_module')
                    ->where('module_id', $request->module_id)
                    ->first();

                if ($existingModule) {
                    return $this->sendError('Validation failed', ['module_id' => ['The module_id has already been taken.']], 0, 422);
                }
            }

            $moduleData = [
                'title' => $request->title, // Title is required
                'icon' => $request->icon ?? null,
                'description' => $request->description ?? null,
                'module_id' => $request->module_id ?? null,
                'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ];

            $id = DB::connection('bcmis2')->table('bridge_module')->insertGetId($moduleData);

            $module = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            Log::info('Bridge module created successfully', [
                'module_id' => $id,
                'title' => $request->title,
                'created_by' => auth()->id()
            ]);

            return $this->sendResponse($module, 'Bridge module created successfully');

        } catch (\Exception $e) {
            Log::error('Error creating bridge module: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create bridge module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified bridge module.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $module = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            return $this->sendResponse($module, 'Bridge module retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching bridge module: ' . $e->getMessage(), [
                'module_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve bridge module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update the specified bridge module.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string|max:255', // Title field
                'icon' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'module_id' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if module_id is unique (manual check for cross-database connection)
            if ($request->has('module_id') && $request->module_id) {
                $existingModule = DB::connection('bcmis2')->table('bridge_module')
                    ->where('module_id', $request->module_id)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingModule) {
                    return $this->sendError('Validation failed', ['module_id' => ['The module_id has already been taken.']], 0, 422);
                }
            }

            // Check if module exists
            $module = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            $updateData = [
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ];

            if ($request->has('title')) {
                $updateData['title'] = $request->title;
            }

            if ($request->has('icon')) {
                $updateData['icon'] = $request->icon;
            }

            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }

            if ($request->has('module_id')) {
                $updateData['module_id'] = $request->module_id;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = (bool) $request->is_active;
            }

            DB::connection('bcmis2')->table('bridge_module')
                ->where('id', $id)
                ->update($updateData);

            $updatedModule = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            Log::info('Bridge module updated successfully', [
                'module_id' => $id,
                'modified_by' => auth()->id()
            ]);

            return $this->sendResponse($updatedModule, 'Bridge module updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating bridge module: ' . $e->getMessage(), [
                'module_id' => $id,
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to update bridge module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove the specified bridge module.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $module = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            DB::connection('bcmis2')->table('bridge_module')->where('id', $id)->delete();

            Log::info('Bridge module deleted successfully', [
                'module_id' => $id
            ]);

            return $this->sendResponse(null, 'Bridge module deleted successfully');

        } catch (\Exception $e) {
            Log::error('Error deleting bridge module: ' . $e->getMessage(), [
                'module_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to delete bridge module: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle the active status of a bridge module.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $module = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            if (!$module) {
                return $this->sendError('Bridge module not found', [], 0, 404);
            }

            $newStatus = !$module->is_active;

            DB::connection('bcmis2')->table('bridge_module')
                ->where('id', $id)
                ->update([
                    'is_active' => $newStatus,
                    'modified_by' => auth()->id(),
                    'modified_at' => now(),
                ]);

            $updatedModule = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.id', $id)
                ->first();

            Log::info('Bridge module status toggled', [
                'module_id' => $id,
                'new_status' => $newStatus,
                'modified_by' => auth()->id()
            ]);

            return $this->sendResponse($updatedModule, 'Bridge module status updated successfully');

        } catch (\Exception $e) {
            Log::error('Error toggling bridge module status: ' . $e->getMessage(), [
                'module_id' => $id,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to toggle bridge module status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get active bridge modules.
     *
     * @return JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $modules = DB::connection('bcmis2')
                ->table('bridge_module')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bridge_module.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bridge_module.modified_by')
                ->select(
                    'bridge_module.*',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bridge_module.is_active', true)
                ->orderBy('bridge_module.title', 'asc')
                ->get();

            return $this->sendResponse($modules, 'Active bridge modules retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active bridge modules: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->sendError('Failed to retrieve active bridge modules: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

