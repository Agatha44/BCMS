<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\EducationalLevel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EducationalLevelController extends Controller
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
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.educational_levels as el')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'el.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'el.modified_by')
                ->select(
                    'el.id',
                    'el.level_name',
                    'el.is_active',
                    'el.created_at',
                    'el.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where('el.level_name', 'like', "%{$search}%");
            }

            // Status filter
            if ($status !== null) {
                $query->where('el.is_active', $status);
            }

            // Sorting
            $query->orderBy('el.' . $sortBy, $sortOrder);

            // Pagination
            $educationalLevels = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Educational levels retrieved successfully',
                'data' => [ $educationalLevels->items(),
                    'pagination' => [
                        'current_page' => $educationalLevels->currentPage(),
                        'last_page' => $educationalLevels->lastPage(),
                        'per_page' => $educationalLevels->perPage(),
                        'total' => $educationalLevels->total(),
                        'from' => $educationalLevels->firstItem(),
                        'to' => $educationalLevels->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve educational levels: ' . $e->getMessage()
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
            // Check if level_name already exists
            $existingLevel = EducationalLevel::where('level_name', $request->level_name)->first();
            if ($existingLevel) {
                return response()->json([
                    'success' => false,
                    'errors' => ['level_name' => ['The educational level name has already been taken.']],
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'level_name' => 'required|string|max:100',
                'is_active' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $educationalLevel = EducationalLevel::create([
                'level_name' => $request->level_name,
                'is_active' => $request->is_active,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Educational level created successfully',
                'data' => $educationalLevel
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create educational level: ' . $e->getMessage()
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
            $educationalLevel = EducationalLevel::find($id);

            if (!$educationalLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Educational level not found'
                ], 404);
            }

            $educationalLevelData = DB::table('bcmis2.educational_levels as el')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'el.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'el.modified_by')
                ->where('el.id', $id)
                ->select(
                    'el.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Educational level retrieved successfully',
                'data' => $educationalLevelData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve educational level: ' . $e->getMessage()
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
            $educationalLevel = EducationalLevel::find($id);

            if (!$educationalLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Educational level not found'
                ], 404);
            }

            // Check if level_name already exists (excluding current level)
            if ($request->has('level_name')) {
                $existingLevel = EducationalLevel::where('level_name', $request->level_name)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingLevel) {
                    return response()->json([
                        'success' => false,
                        'errors' => ['level_name' => ['The educational level name has already been taken.']],
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), [
                'level_name' => 'sometimes|required|string|max:100',
                'is_active' => 'sometimes|required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('level_name')) {
                $updateData['level_name'] = $request->level_name;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $educationalLevel->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Educational level updated successfully',
                'data' => $educationalLevel
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update educational level: ' . $e->getMessage()
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
            $educationalLevel = EducationalLevel::find($id);

            if (!$educationalLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Educational level not found'
                ], 404);
            }

            // Check if educational level is being used in overtime rates
            $hasOvertimeRates = DB::table('bcmis2.overtime_rates')
                ->where('educational_levels_id', $id)
                ->exists();

            if ($hasOvertimeRates) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete educational level. It has associated overtime rates.'
                ], 422);
            }

            $educationalLevel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Educational level deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete educational level: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle educational level status
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $educationalLevel = EducationalLevel::find($id);

            if (!$educationalLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Educational level not found'
                ], 404);
            }

            $educationalLevel->is_active = !$educationalLevel->is_active;
            $educationalLevel->modified_by = auth()->id();
            $educationalLevel->modified_at = now();
            $educationalLevel->save();

            return response()->json([
                'success' => true,
                'message' => 'Educational level status updated successfully',
                'data' => $educationalLevel
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update educational level status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active educational levels
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveLevels(): JsonResponse
    {
        try {
            $educationalLevels = EducationalLevel::where('is_active', true)
                ->orderBy('level_name', 'asc')
                ->get(['id', 'level_name', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active educational levels retrieved successfully',
                'data' => $educationalLevels
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active educational levels: ' . $e->getMessage()
            ], 500);
        }
    }
}
