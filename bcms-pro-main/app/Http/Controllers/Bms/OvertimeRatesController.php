<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\OvertimeRates;
use App\Models\Bms\EducationalLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OvertimeRatesController extends Controller
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
            $status = $request->get('status');
            $educationalLevelId = $request->get('educational_level_id');
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.overtime_rates as or')
                ->leftJoin('bcmis2.educational_levels as el', 'el.id', '=', 'or.educational_levels_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'or.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'or.modified_by')
                ->select(
                    'or.id',
                    'or.created_at',
                    'or.modified_at',
                    'or.is_active',
                    'or.rate',
                    'el.level_name as educational_level_name',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('el.level_name', 'like', "%{$search}%")
                      ->orWhere('or.rate', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($status !== null) {
                $query->where('or.is_active', $status);
            }

            // Educational level filter
            if ($educationalLevelId !== null) {
                $query->where('or.educational_levels_id', $educationalLevelId);
            }

            // Sorting
            $query->orderBy('or.' . $sortBy, $sortOrder);

            // Pagination
            $overtimeRates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Overtime rates retrieved successfully',
                'data' => [$overtimeRates->items(),
                    'pagination' => [
                        'current_page' => $overtimeRates->currentPage(),
                        'last_page' => $overtimeRates->lastPage(),
                        'per_page' => $overtimeRates->perPage(),
                        'total' => $overtimeRates->total(),
                        'from' => $overtimeRates->firstItem(),
                        'to' => $overtimeRates->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overtime rates: ' . $e->getMessage()
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
                'educational_levels_id' => 'required|integer|exists:bcmis2.educational_levels,id',
                'rate' => 'required|integer|min:0',
                'is_active' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if rate already exists for this educational level
            $existingRate = OvertimeRates::where('educational_levels_id', $request->educational_levels_id)
                ->first();

            if ($existingRate) {
                return response()->json([
                    'success' => false,
                    'errors' => ['educational_levels_id' => ['Overtime rate already exists for this educational level.']],
                ], 422);
            }

            $overtimeRate = OvertimeRates::create([
                'educational_levels_id' => $request->educational_levels_id,
                'rate' => $request->rate,
                'is_active' => $request->is_active,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            // Load the relationship
            $overtimeRate->load('educationalLevel');

            return response()->json([
                'success' => true,
                'message' => 'Overtime rate created successfully',
                'data' => $overtimeRate
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create overtime rate: ' . $e->getMessage()
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
            $overtimeRate = OvertimeRates::with('educationalLevel')->find($id);

            if (!$overtimeRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime rate not found'
                ], 404);
            }

            $overtimeRateData = DB::table('bcmis2.overtime_rates as or')
                ->leftJoin('bcmis2.educational_levels as el', 'el.id', '=', 'or.educational_levels_id')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'or.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'or.modified_by')
                ->where('or.id', $id)
                ->select(
                    'or.*',
                    'el.level_name as educational_level_name',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Overtime rate retrieved successfully',
                'data' => $overtimeRateData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overtime rate: ' . $e->getMessage()
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
            $overtimeRate = OvertimeRates::find($id);

            if (!$overtimeRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime rate not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'educational_levels_id' => 'sometimes|required|integer|exists:bcmis2.educational_levels,id',
                'rate' => 'sometimes|required|integer|min:0',
                'is_active' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if rate already exists for this educational level (excluding current record)
            if ($request->has('educational_levels_id')) {
                $existingRate = OvertimeRates::where('educational_levels_id', $request->educational_levels_id)
                    ->where('id', '!=', $id)
                    ->first();

                if ($existingRate) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['educational_levels_id' => ['Overtime rate already exists for this educational level.']],
                    ], 422);
                }
            }

            $updateData = [];
            if ($request->has('educational_levels_id')) {
                $updateData['educational_levels_id'] = $request->educational_levels_id;
            }
            if ($request->has('rate')) {
                $updateData['rate'] = $request->rate;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $overtimeRate->update($updateData);

            // Reload the relationship
            $overtimeRate->load('educationalLevel');

            return response()->json([
                'success' => true,
                'message' => 'Overtime rate updated successfully',
                'data' => $overtimeRate
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update overtime rate: ' . $e->getMessage()
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
            $overtimeRate = OvertimeRates::find($id);

            if (!$overtimeRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime rate not found'
                ], 404);
            }

            $overtimeRate->delete();

            return response()->json([
                'success' => true,
                'message' => 'Overtime rate deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete overtime rate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle overtime rate status
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $overtimeRate = OvertimeRates::find($id);

            if (!$overtimeRate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Overtime rate not found'
                ], 404);
            }

            $overtimeRate->is_active = !$overtimeRate->is_active;
            $overtimeRate->modified_by = auth()->id();
            $overtimeRate->modified_at = now();
            $overtimeRate->save();

            // Reload the relationship
            $overtimeRate->load('educationalLevel');

            return response()->json([
                'success' => true,
                'message' => 'Overtime rate status updated successfully',
                'data' => $overtimeRate
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update overtime rate status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active overtime rates
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveRates(): JsonResponse
    {
        try {
            $overtimeRates = OvertimeRates::with('educationalLevel')
                ->where('is_active', true)
                ->orderBy('rate', 'asc')
                ->get(['id', 'educational_levels_id', 'rate', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active overtime rates retrieved successfully',
                'data' => $overtimeRates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active overtime rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overtime rates by educational level
     *
     * @param  int  $educationalLevelId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByEducationalLevel($educationalLevelId): JsonResponse
    {
        try {
            $overtimeRates = OvertimeRates::with('educationalLevel')
                ->where('educational_levels_id', $educationalLevelId)
                ->orderBy('rate', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Overtime rates retrieved successfully',
                'data' => $overtimeRates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overtime rates: ' . $e->getMessage()
            ], 500);
        }
    }
}
