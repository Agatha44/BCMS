<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\BridgeEmploymentType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BridgeEmploymentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $sortBy = $request->get('sort_by', 'emptype_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.bridge_employment_type as et')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'et.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'et.modified_by')
                ->select(
                    'et.emptype_id',
                    'et.emptype_name',
                    'et.prob_months',
                    'et.is_active',
                    'et.created_at',
                    'et.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('et.emptype_name', 'like', "%{$search}%");
                });
            }

            // Sorting
            $query->orderBy('et.' . $sortBy, $sortOrder);

            // Pagination
            $employmentTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employment types retrieved successfully',
                'data' => [
                    'employment_types' => $employmentTypes->items(),
                    'pagination' => [
                        'current_page' => $employmentTypes->currentPage(),
                        'last_page' => $employmentTypes->lastPage(),
                        'per_page' => $employmentTypes->perPage(),
                        'total' => $employmentTypes->total(),
                        'from' => $employmentTypes->firstItem(),
                        'to' => $employmentTypes->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employment types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'emptype_name' => 'required|string|max:100',
                'prob_months' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if name already exists
            $existingType = BridgeEmploymentType::where('emptype_name', $request->emptype_name)->first();
            if ($existingType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type with this name already exists',
                    'errors' => ['emptype_name' => ['The employment type name has already been taken.']]
                ], 422);
            }

            $employmentType = BridgeEmploymentType::create([
                'emptype_name' => $request->emptype_name,
                'prob_months' => $request->prob_months,
                'is_active' => $request->get('is_active', true),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employment type created successfully',
                'data' => $employmentType
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            // Validate that ID is provided and is a valid integer
            if (empty($id) || $id === 'undefined' || $id === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type ID is required'
                ], 400);
            }

            // Convert to integer if it's a numeric string
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid employment type ID'
                ], 400);
            }

            $id = (int) $id;
            $employmentTypeData = DB::table('bcmis2.bridge_employment_type as et')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'et.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'et.modified_by')
                ->where('et.emptype_id', $id)
                ->select(
                    'et.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            if (!$employmentTypeData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employment type retrieved successfully',
                'data' => $employmentTypeData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employment type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all employment types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(): JsonResponse
    {
        try {
            $employmentTypes = BridgeEmploymentType::orderBy('emptype_name', 'asc')
                ->get(['emptype_id', 'emptype_name', 'prob_months', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Employment types retrieved successfully',
                'data' => $employmentTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employment types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active employment types
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveBridgeEmploymentTypes(): JsonResponse
    {
        try {
            $employmentTypes = BridgeEmploymentType::where('is_active', true)
                ->orderBy('emptype_name', 'asc')
                ->get(['emptype_id', 'emptype_name', 'prob_months', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active employment types retrieved successfully',
                'data' => $employmentTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active employment types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Validate that ID is provided and is a valid integer
            if (empty($id) || $id === 'undefined' || $id === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type ID is required'
                ], 400);
            }

            // Convert to integer if it's a numeric string
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid employment type ID'
                ], 400);
            }

            $id = (int) $id;
            $employmentType = BridgeEmploymentType::find($id);

            if (!$employmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }

            // Check if name already exists (excluding current type)
            if ($request->has('emptype_name')) {
                $existingType = BridgeEmploymentType::where('emptype_name', $request->emptype_name)
                    ->where('emptype_id', '!=', $id)
                    ->first();
                if ($existingType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Employment type with this name already exists',
                        'errors' => ['emptype_name' => ['The employment type name has already been taken.']]
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), [
                'emptype_name' => 'sometimes|required|string|max:100',
                'prob_months' => 'sometimes|nullable|integer|min:0',
                'is_active' => 'sometimes|nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('emptype_name')) {
                $updateData['emptype_name'] = $request->emptype_name;
            }
            if ($request->has('prob_months')) {
                $updateData['prob_months'] = $request->prob_months;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $employmentType->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Employment type updated successfully',
                'data' => $employmentType
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Validate that ID is provided and is a valid integer
            if (empty($id) || $id === 'undefined' || $id === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type ID is required'
                ], 400);
            }

            // Convert to integer if it's a numeric string
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid employment type ID'
                ], 400);
            }

            $id = (int) $id;
            $employmentType = BridgeEmploymentType::find($id);

            if (!$employmentType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment type not found'
                ], 404);
            }

            // Check if employment type is being used in bridge_employee table
            $hasEmployees = DB::table('bcmis2.bridge_employee')
                ->where('emptype_id', $id)
                ->exists();

            if ($hasEmployees) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete employment type. It has associated employees.'
                ], 422);
            }

            $employmentType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employment type deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employment type: ' . $e->getMessage()
            ], 500);
        }
    }
}

