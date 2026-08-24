<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\BasicController;
use App\Models\Bms\BridgeShiftDepartment;
use App\Models\Bms\BridgeShift;
use App\Models\Bms\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class BridgeShiftDepartmentController extends BasicController
{
    /**
     * Filter all department-shift mapping details with pagination, search and sorting.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $shiftId = $request->input('shift_id');
            $departmentId = $request->input('department_id');
            $isActive = $request->input('is_active');
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_shift_department as bsd')
                ->leftJoin('bridge_shifts as bs', 'bsd.shift_id', '=', 'bs.id')
                ->leftJoin('departments as d', 'bsd.department_id', '=', 'd.department_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bsd.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bsd.modified_by')
                ->select(
                    'bsd.*',
                    'bs.shift_name',
                    'bs.start_time',
                    'bs.end_time',
                    'bs.is_active as shift_is_active',
                    'd.department_name',
                    'd.is_active as department_is_active',
                    'bsd.created_at',
                    'bsd.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Apply shift filter
            if ($shiftId) {
                $query->where('bsd.shift_id', $shiftId);
            }

            // Apply department filter
            if ($departmentId) {
                $query->where('bsd.department_id', $departmentId);
            }

            // Apply active filter
            if ($isActive !== null) {
                $query->where('bsd.is_active', $isActive);
            }

            // Search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bs.shift_name', 'like', "%{$search}%")
                        ->orWhere('d.department_name', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $query->orderBy('bsd.' . $sortBy, $sortOrder);

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
            ], 'Department-shift mappings retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching department-shift mappings: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve department-shift mappings: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Get all active department-shift mappings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getActiveDepartmentShiftAssignment(Request $request): JsonResponse
    {
        try {
            $shiftId = $request->input('shift_id');
            $departmentId = $request->input('department_id');
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'id');
            $sortOrder = $request->input('sort_order', 'asc');

            $query = DB::connection('bcmis2')
                ->table('bridge_shift_department as bsd')
                ->leftJoin('bridge_shifts as bs', 'bsd.shift_id', '=', 'bs.id')
                ->leftJoin('departments as d', 'bsd.department_id', '=', 'd.department_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bsd.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bsd.modified_by')
                ->select(
                    'bsd.*',
                    'bs.shift_name',
                    'bs.start_time',
                    'bs.end_time',
                    'bs.is_active as shift_is_active',
                    'd.department_name',
                    'd.is_active as department_is_active',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bsd.is_active', true);

            // Apply shift filter
            if ($shiftId) {
                $query->where('bsd.shift_id', $shiftId);
            }

            // Apply department filter
            if ($departmentId) {
                $query->where('bsd.department_id', $departmentId);
            }

            // Apply sorting
            $query->orderBy('bsd.' . $sortBy, $sortOrder);

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
            ], 'Active department-shift mappings retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching active department-shift mappings: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve active department-shift mappings: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Map department and shift on pivot table (create new mapping).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createAssignment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'shift_id' => 'required|integer|exists:bcmis2.bridge_shifts,id',
                'department_id' => 'required|integer|exists:bcmis2.departments,department_id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check if mapping already exists
            $existing = BridgeShiftDepartment::where('shift_id', $request->shift_id)
                ->where('department_id', $request->department_id)
                ->first();

            if ($existing) {
                return $this->sendError('Department-shift mapping already exists', [], 0, 422);
            }

            // Verify shift exists
            $shift = BridgeShift::find($request->shift_id);
            if (!$shift) {
                return $this->sendError('Shift not found', [], 0, 404);
            }

            // Verify department exists
            $department = Department::find($request->department_id);
            if (!$department) {
                return $this->sendError('Department not found', [], 0, 404);
            }

            $assignment = BridgeShiftDepartment::create([
                'shift_id' => $request->shift_id,
                'department_id' => $request->department_id,
                'is_active' => $request->is_active,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            // Load relationships for response
            $assignment->load(['shift', 'department']);

            return $this->sendResponse($assignment, 'Department-shift assignment created successfully');

        } catch (\Exception $e) {
            Log::error('Error Assigning department to shift: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to assign department to shift: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Display the specified department-shift mapping.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $assignment = DB::connection('bcmis2')
                ->table('bridge_shift_department as bsd')
                ->leftJoin('bridge_shifts as bs', 'bsd.shift_id', '=', 'bs.id')
                ->leftJoin('departments as d', 'bsd.department_id', '=', 'd.department_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bsd.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bsd.modified_by')
                ->select(
                    'bsd.*',
                    'bs.shift_name',
                    'bs.start_time',
                    'bs.end_time',
                    'bs.is_active as shift_is_active',
                    'd.department_name',
                    'd.is_active as department_is_active',
                    'bsd.created_at',
                    'bsd.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->where('bsd.id', $id)
                ->first();

            if (!$assignment) {
                return $this->sendError('Department-shift assignment not found', [], 0, 404);
            }

            return $this->sendResponse($assignment, 'Department-shift assignment retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Error fetching department-shift assignment: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve department-shift assignment: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Update department-shift mapping.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateDepartmentShiftAssignment(Request $request, $id): JsonResponse
    {
        try {
            $assignment = BridgeShiftDepartment::find($id);

            if (!$assignment) {
                return $this->sendError('Department-shift assignment not found', [], 0, 404);
            }

            $validator = Validator::make($request->all(), [
                'shift_id' => 'sometimes|required|integer|exists:bcmis2.bridge_shifts,id',
                'department_id' => 'sometimes|required|integer|exists:bcmis2.departments,department_id',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation failed', $validator->errors()->toArray(), 0, 422);
            }

            // Check for duplicate if shift_id or department_id is being updated
            if ($request->has('shift_id') || $request->has('department_id')) {
                $newShiftId = $request->has('shift_id') ? $request->shift_id : $assignment->shift_id;
                $newDepartmentId = $request->has('department_id') ? $request->department_id : $assignment->department_id;

                $exists = BridgeShiftDepartment::where('shift_id', $newShiftId)
                    ->where('department_id', $newDepartmentId)
                    ->where('id', '!=', $id)
                    ->first();

                if ($exists) {
                    return $this->sendError('Department-shift assignment already exists with these values', [], 0, 422);
                }
            }

            $updateData = [];

            if ($request->has('shift_id')) {
                $updateData['shift_id'] = $request->shift_id;
            }

            if ($request->has('department_id')) {
                $updateData['department_id'] = $request->department_id;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            if (!empty($updateData)) {
                $updateData['modified_by'] = auth()->id();
                $updateData['modified_at'] = now();
                $assignment->update($updateData);
            }

            // Reload relationships
            $assignment->load(['shift', 'department']);

            return $this->sendResponse($assignment, 'Department-shift assignment updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating department-shift assignment: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update department-shift mapping: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Toggle status of department-shift mapping.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function toggleDepartmentShiftAssignmentStatus($id): JsonResponse
    {
        try {
            $assignment = BridgeShiftDepartment::find($id);

            if (!$assignment) {
                return $this->sendError('Department-shift mapping not found', [], 0, 404);
            }

            $assignment->is_active = !$assignment->is_active;
            $assignment->modified_by = auth()->id();
            $assignment->modified_at = now();
            $assignment->save();

            // Reload relationships
            $assignment->load(['shift', 'department']);

            return $this->sendResponse($assignment, 'Department-shift mapping status updated successfully');

        } catch (\Exception $e) {
            Log::error('Error toggling department-shift mapping status: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to toggle department-shift mapping status: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Remove mapping of department and shift.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            $assignment = BridgeShiftDepartment::find($id);

            if (!$assignment) {
                return $this->sendError('Department-shift mapping not found', [], 0, 404);
            }

            $assignment->delete();

            return $this->sendResponse([], 'Department-shift mapping removed successfully');

        } catch (\Exception $e) {
            Log::error('Error removing department-shift mapping: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to remove department-shift mapping: ' . $e->getMessage(), [], 0, 500);
        }
    }
}

