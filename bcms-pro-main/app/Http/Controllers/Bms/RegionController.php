<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\Domicile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RegionController extends Controller
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

            $query = DB::table('bcmis2.bridge_domicile as d')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'd.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'd.modified_by')
                ->select(
                    'd.id',
                    'd.name',
                    'd.description',
                    'd.is_active',
                    'd.created_at',
                    'd.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where('d.name', 'like', "%{$search}%");
            }

            // Status filter
            if ($status !== null) {
                $query->where('d.is_active', $status);
            }

            // Sorting
            $query->orderBy('d.' . $sortBy, $sortOrder);

            // Pagination
            $regions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Regions retrieved successfully',
                'data' => [
                    'regions' => $regions->items(),
                    'pagination' => [
                        'current_page' => $regions->currentPage(),
                        'last_page' => $regions->lastPage(),
                        'per_page' => $regions->perPage(),
                        'total' => $regions->total(),
                        'from' => $regions->firstItem(),
                        'to' => $regions->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve regions: ' . $e->getMessage()
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
                'name' => 'required|string|max:100',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if name already exists
            $existingRegion = Domicile::where('name', $request->name)->first();
            if ($existingRegion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region with this name already exists',
                    'errors' => ['name' => ['The region name has already been taken.']]
                ], 422);
            }

            $region = Domicile::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Region created successfully',
                'data' => $region
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create region: ' . $e->getMessage()
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
            $region = Domicile::find($id);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            $regionData = DB::table('bcmis2.bridge_domicile as d')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'd.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'd.modified_by')
                ->where('d.id', $id)
                ->select(
                    'd.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Region retrieved successfully',
                'data' => $regionData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve region: ' . $e->getMessage()
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
            $region = Domicile::find($id);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            // Check if name already exists (excluding current region)
            if ($request->has('name')) {
                $existingRegion = Domicile::where('name', $request->name)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingRegion) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Region with this name already exists',
                        'errors' => ['name' => ['The region name has already been taken.']]
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'description' => 'sometimes|nullable|string',
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
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $region->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Region updated successfully',
                'data' => $region
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update region: ' . $e->getMessage()
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
            $region = Domicile::find($id);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            // Check if region has associated districts
            $hasDistricts = DB::table('bcmis2.bridge_district')
                ->where('domicile_id', $id)
                ->exists();

            if ($hasDistricts) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete region. It has associated districts.'
                ], 422);
            }

            $region->delete();

            return response()->json([
                'success' => true,
                'message' => 'Region deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete region: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle region status
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $region = Domicile::find($id);

            if (!$region) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region not found'
                ], 404);
            }

            $region->is_active = !$region->is_active;
            $region->modified_by = auth()->id();
            $region->modified_at = now();
            $region->save();

            return response()->json([
                'success' => true,
                'message' => 'Region status updated successfully',
                'data' => $region
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update region status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active regions
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $regions = Domicile::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active regions retrieved successfully',
                'data' => $regions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active regions: ' . $e->getMessage()
            ], 500);
        }
    }
}
