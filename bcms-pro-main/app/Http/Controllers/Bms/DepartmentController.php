<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends BasicController
{
    /**
     * Create a new department
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createDepartment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'department_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            $department = new Department();
            $department->department_name = $request->department_name;
            $department->is_active = $request->is_active;
            $department->created_by = (string) auth()->id();
            $department->created_at = now();

            if ($department->save()) {
                DB::connection('bcmis2')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Department created successfully',
                    'data' => $department
                ], 201);
            } else {
                DB::connection('bcmis2')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create department'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to create department', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all departments with pagination and filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listDepartments(Request $request): JsonResponse
    {
        try {
            $query = DB::connection('bcmis2')
                ->table('departments as d')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'd.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'd.modified_by')
                ->select([
                    'd.department_id',
                    'd.department_name',
                    'd.is_active',
                    'd.created_at',
                    'd.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by"),
                ]);

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('d.department_name', 'like', "%{$search}%");
            }

            // Filter by is_active
            if ($request->has('is_active') && $request->is_active !== null) {
                $query->where('d.is_active', $request->is_active);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'department_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy('d.' . $sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $departments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Departments retrieved successfully',
                'data' => [
                    'departments' => $departments->items(),
                    'pagination' => [
                        'current_page' => $departments->currentPage(),
                        'last_page' => $departments->lastPage(),
                        'per_page' => $departments->perPage(),
                        'total' => $departments->total(),
                        'from' => $departments->firstItem(),
                        'to' => $departments->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve departments', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active departments
     *
     * @return JsonResponse
     */
    public function getActiveDepartments(): JsonResponse
    {
        try {
            $departments = Department::where('is_active', true)
                ->orderBy('department_name', 'asc')
                ->get([
                    'department_id',
                    'department_name',
                    'is_active',
                    'created_at'
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Active departments retrieved successfully',
                'data' => $departments
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve active departments', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active departments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific department by ID
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getDepartmentById($id): JsonResponse
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Department retrieved successfully',
                'data' => $department
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve department', [
                'department_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing department
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateDepartment(Request $request, $id): JsonResponse
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'department_name' => 'sometimes|required|string|max:255',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::connection('bcmis2')->beginTransaction();

            // Update only provided fields
            if ($request->has('department_name')) {
                $department->department_name = $request->department_name;
            }
            if ($request->has('is_active')) {
                $department->is_active = $request->is_active;
            }
            
            $department->modified_by = (string) auth()->id();
            $department->modified_at = now();

            if ($department->save()) {
                DB::connection('bcmis2')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Department updated successfully',
                    'data' => $department
                ]);
            } else {
                DB::connection('bcmis2')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update department'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to update department', [
                'department_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle department status
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found'
                ], 404);
            }

            DB::connection('bcmis2')->beginTransaction();

            $department->is_active = !$department->is_active;
            $department->modified_by = (string) auth()->id();
            $department->modified_at = now();

            if ($department->save()) {
                DB::connection('bcmis2')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Department status updated successfully',
                    'data' => $department
                ]);
            } else {
                DB::connection('bcmis2')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update department status'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to toggle department status', [
                'department_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle department status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a department
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteDepartment($id): JsonResponse
    {
        try {
            $department = Department::find($id);

            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department not found'
                ], 404);
            }

            DB::connection('bcmis2')->beginTransaction();

            if ($department->delete()) {
                DB::connection('bcmis2')->commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Department deleted successfully'
                ]);
            } else {
                DB::connection('bcmis2')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete department'
                ], 500);
            }

        } catch (\Exception $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to delete department', [
                'department_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department: ' . $e->getMessage()
            ], 500);
        }
    }
}
