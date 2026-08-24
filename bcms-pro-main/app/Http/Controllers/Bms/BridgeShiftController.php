<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\BridgeShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BridgeShiftController extends Controller
{
    /**
     * List bridge shifts with pagination, search and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = (int) $request->get('per_page', 15);

            $query = DB::connection('bcmis2')
                ->table('bridge_shifts as bs')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bs.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bs.modified_by')
                ->select([
                    'bs.id',
                    'bs.shift_name',
                    'bs.start_time',
                    'bs.end_time',
                    'bs.is_active',
                    'bs.created_at',
                    'bs.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by"),
                ]);

            // Search by shift name
            if ($search) {
                $query->where('bs.shift_name', 'like', "%{$search}%");
            }

            // Apply sorting
            $query->orderBy('bs.' . $sortBy, $sortOrder);

            // Paginate
            $paginator = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Bridge shifts retrieved successfully',
                'data' => [
                    'shifts' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bridge shifts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all bridge shifts (no pagination).
     */
     public function getAllShifts(Request $request): JsonResponse
    {
        try {
            $shifts = BridgeShift::query()
                ->orderBy('shift_name', 'asc')
                ->get([
                    'id',
                    'shift_name',
                    'start_time',
                    'end_time',
                    'is_active',
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Bridge shifts retrieved successfully',
                'data' => $shifts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bridge shifts: ' . $e->getMessage(),
            ], 500);
        }
    }
 
     /**
      * Get active bridge shifts (no pagination).
      */
     public function getActiveShifts(Request $request): JsonResponse
     {
         try {
             $shifts = DB::connection('bcmis2')
                 ->table('bridge_shifts as bs')
                 ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bs.created_by')
                 ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bs.modified_by')
                 ->where('bs.is_active', true)
                 ->orderBy('bs.shift_name', 'asc')
                 ->select([
                     'bs.id',
                     'bs.shift_name',
                     'bs.start_time',
                     'bs.end_time',
                     'bs.is_active',
                     'bs.created_at',
                     'bs.modified_at',
                     DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                     DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by"),
                 ])
                 ->get();

             return response()->json([
                 'success' => true,
                 'message' => 'Active bridge shifts retrieved successfully',
                 'data' => $shifts,
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Failed to retrieve active bridge shifts: ' . $e->getMessage(),
             ], 500);
         }
     }

    /**
     * Store a newly created bridge shift.
     */
    public function RegisterShifts(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'shift_name' => 'required|string|max:100',
                'is_active' => 'required|boolean',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $shift = BridgeShift::create([
                'shift_name' => $request->shift_name,
                'is_active' => $request->boolean('is_active'),
                'start_time' => $request->start_time . ':00',
                'end_time' => $request->end_time . ':00',
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bridge shift created successfully',
                'data' => $shift,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bridge shift: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified bridge shift.
     */
    public function showShifts($id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bridge shift ID',
                ], 400);
            }

            $id = (int) $id;

            $shift = BridgeShift::find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bridge shift not found',
                ], 404);
            }

            // Get creator and modifier names
            $createdBy = DB::table('bcmis.auth_user')
                ->where('id', $shift->created_by)
                ->select(DB::raw("CONCAT(first_name, ' ', surname) AS name"))
                ->value('name');

            $modifiedBy = $shift->modified_by ? DB::table('bcmis.auth_user')
                ->where('id', $shift->modified_by)
                ->select(DB::raw("CONCAT(first_name, ' ', surname) AS name"))
                ->value('name') : null;

            $shiftData = $shift->toArray();
            $shiftData['created_by'] = $createdBy;
            $shiftData['modified_by'] = $modifiedBy;

            return response()->json([
                'success' => true,
                'message' => 'Bridge shift retrieved successfully',
                'data' => $shiftData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bridge shift: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified bridge shift.
     */
    public function updateShifts(Request $request, $id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bridge shift ID',
                ], 400);
            }

            $id = (int) $id;
            $shift = BridgeShift::find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bridge shift not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'shift_name' => 'sometimes|required|string|max:100',
                'is_active' => 'sometimes|required|boolean',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $updateData = [];

            if ($request->has('shift_name')) {
                $updateData['shift_name'] = $request->shift_name;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            if ($request->has('start_time')) {
                $updateData['start_time'] = $request->start_time . ':00';
            }
            if ($request->has('end_time')) {
                $updateData['end_time'] = $request->end_time . ':00';
            }

            if (!empty($updateData)) {
                $updateData['modified_by'] = auth()->id();
                $updateData['modified_at'] = now();
                $shift->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Bridge shift updated successfully',
                'data' => $shift,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bridge shift: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle bridge shift status (active/inactive).
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bridge shift ID',
                ], 400);
            }

            $id = (int) $id;
            $shift = BridgeShift::find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bridge shift not found',
                ], 404);
            }

            $shift->is_active = !$shift->is_active;
            $shift->modified_by = auth()->id();
            $shift->modified_at = now();
            $shift->save();

            return response()->json([
                'success' => true,
                'message' => 'Bridge shift status updated successfully',
                'data' => $shift,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bridge shift status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified bridge shift.
     */
    public function destroyShift($id): JsonResponse
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid bridge shift ID',
                ], 400);
            }

            $id = (int) $id;
            $shift = BridgeShift::find($id);

            if (!$shift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bridge shift not found',
                ], 404);
            }

            $shift->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bridge shift deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bridge shift: ' . $e->getMessage(),
            ], 500);
        }
    }
    
}

