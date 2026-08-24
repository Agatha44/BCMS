<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\District;
use App\Models\Bms\Domicile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DistrictController extends Controller
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
            $domicileId = $request->get('domicile_id');
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.bridge_district as dist')
                ->leftJoin('bcmis2.bridge_domicile as dom', 'dom.id', '=', 'dist.domicile_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'dist.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'dist.modified_by')
                ->select(
                    'dist.id',
                    'dist.name',
                    'dist.domicile_id',
                    'dom.name as domicile_name',
                    'dist.description',
                    'dist.is_active',
                    'dist.created_at',
                    'dist.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('dist.name', 'like', "%{$search}%")
                      ->orWhere('dom.name', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($status !== null) {
                $query->where('dist.is_active', $status);
            }

            // Domicile filter
            if ($domicileId) {
                $query->where('dist.domicile_id', $domicileId);
            }

            // Sorting
            $query->orderBy('dist.' . $sortBy, $sortOrder);

            // Pagination
            $districts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Districts retrieved successfully',
                'data' => [
                    'districts' => $districts->items(),
                    'pagination' => [
                        'current_page' => $districts->currentPage(),
                        'last_page' => $districts->lastPage(),
                        'per_page' => $districts->perPage(),
                        'total' => $districts->total(),
                        'from' => $districts->firstItem(),
                        'to' => $districts->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve districts: ' . $e->getMessage()
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
                'domicile_id' => 'required|integer|exists:bcmis2.bridge_domicile,id',
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

            // Check if domicile exists
            $domicile = Domicile::find($request->domicile_id);
            if (!$domicile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Region (domicile) not found',
                    'errors' => ['domicile_id' => ['The selected region does not exist.']]
                ], 422);
            }

            // Check if name already exists for this domicile
            $existingDistrict = District::where('name', $request->name)
                ->where('domicile_id', $request->domicile_id)
                ->first();
            if ($existingDistrict) {
                return response()->json([
                    'success' => false,
                    'message' => 'District with this name already exists in this region',
                    'errors' => ['name' => ['The district name has already been taken for this region.']]
                ], 422);
            }

            $district = District::create([
                'name' => $request->name,
                'domicile_id' => $request->domicile_id,
                'description' => $request->description,
                'is_active' => $request->get('is_active', true),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            // Load the domicile relationship
            $district->load('domicile');

            return response()->json([
                'success' => true,
                'message' => 'District created successfully',
                'data' => $district
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create district: ' . $e->getMessage()
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
            $district = District::with('domicile')->find($id);

            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }

            $districtData = DB::table('bcmis2.bridge_district as dist')
                ->leftJoin('bcmis2.bridge_domicile as dom', 'dom.id', '=', 'dist.domicile_id')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'dist.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'dist.modified_by')
                ->where('dist.id', $id)
                ->select(
                    'dist.*',
                    'dom.name as domicile_name',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'District retrieved successfully',
                'data' => $districtData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve district: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active districts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $districts = District::with('domicile:id,name')
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'domicile_id', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active districts retrieved successfully',
                'data' => $districts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active districts: ' . $e->getMessage()
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
            $district = District::find($id);

            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }

            // Check if domicile_id is being changed and validate it exists
            if ($request->has('domicile_id')) {
                $domicile = Domicile::find($request->domicile_id);
                if (!$domicile) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Region (domicile) not found',
                        'errors' => ['domicile_id' => ['The selected region does not exist.']]
                    ], 422);
                }
            }

            // Check if name already exists for this domicile (excluding current district)
            if ($request->has('name')) {
                $domicileId = $request->get('domicile_id', $district->domicile_id);
                $existingDistrict = District::where('name', $request->name)
                    ->where('domicile_id', $domicileId)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingDistrict) {
                    return response()->json([
                        'success' => false,
                        'message' => 'District with this name already exists in this region',
                        'errors' => ['name' => ['The district name has already been taken for this region.']]
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'domicile_id' => 'sometimes|required|integer|exists:bcmis2.bridge_domicile,id',
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
            if ($request->has('domicile_id')) {
                $updateData['domicile_id'] = $request->domicile_id;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $district->update($updateData);

            // Load the domicile relationship
            $district->load('domicile');

            return response()->json([
                'success' => true,
                'message' => 'District updated successfully',
                'data' => $district
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update district: ' . $e->getMessage()
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
            $district = District::find($id);

            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }

            // Check if district is being used in bridge_employee table
            $hasEmployees = DB::table('bcmis2.bridge_employee')
                ->where('district_id', $id)
                ->exists();

            if ($hasEmployees) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete district. It has associated employees.'
                ], 422);
            }

            $district->delete();

            return response()->json([
                'success' => true,
                'message' => 'District deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete district: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle district status
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $district = District::find($id);

            if (!$district) {
                return response()->json([
                    'success' => false,
                    'message' => 'District not found'
                ], 404);
            }

            $district->is_active = !$district->is_active;
            $district->modified_by = auth()->id();
            $district->modified_at = now();
            $district->save();

            return response()->json([
                'success' => true,
                'message' => 'District status updated successfully',
                'data' => $district
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update district status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get districts by domicile (region)
     *
     * @param  int  $domicileId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByDomicile($domicileId): JsonResponse
    {
        try {
            $districts = District::where('domicile_id', $domicileId)
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'domicile_id', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Districts retrieved successfully',
                'data' => $districts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve districts: ' . $e->getMessage()
            ], 500);
        }
    }
}
