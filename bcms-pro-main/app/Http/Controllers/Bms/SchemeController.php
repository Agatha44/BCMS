<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\Scheme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SchemeController extends Controller
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
            $sortBy = $request->get('sort_by', 'scheme_name');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.scheme as scheme')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'scheme.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'scheme.modified_by')
                ->select(
                    'scheme.id',
                    'scheme.scheme_name',
                    'scheme.is_active',
                    'scheme.created_at',
                    'scheme.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('scheme.scheme_name', 'like', "%{$search}%");
                });
            }

            // Sorting
            $query->orderBy('scheme.' . $sortBy, $sortOrder);

            // Pagination
            $schemes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Schemes retrieved successfully',
                'data' => [
                    'schemes' => $schemes->items(),
                    'pagination' => [
                        'current_page' => $schemes->currentPage(),
                        'last_page' => $schemes->lastPage(),
                        'per_page' => $schemes->perPage(),
                        'total' => $schemes->total(),
                        'from' => $schemes->firstItem(),
                        'to' => $schemes->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schemes: ' . $e->getMessage()
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
                'scheme_name' => 'required|string|max:200',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if scheme_name already exists
            $existingScheme = Scheme::where('scheme_name', $request->scheme_name)->first();
            if ($existingScheme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheme with this name already exists',
                    'errors' => ['scheme_name' => ['The scheme name has already been taken.']]
                ], 422);
            }

            $scheme = Scheme::create([
                'scheme_name' => $request->scheme_name,
                'is_active' => $request->get('is_active', true),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Scheme created successfully',
                'data' => $scheme
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create scheme: ' . $e->getMessage()
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
            $scheme = Scheme::find($id);

            if (!$scheme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }

            $schemeData = DB::table('bcmis2.scheme as scheme')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'scheme.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'scheme.modified_by')
                ->where('scheme.id', $id)
                ->select(
                    'scheme.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Scheme retrieved successfully',
                'data' => $schemeData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scheme: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all schemes (for dropdowns)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $schemes = Scheme::ordered()
                ->where('is_active', true)
                ->get(['id', 'scheme_name', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Schemes retrieved successfully',
                'data' => $schemes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schemes: ' . $e->getMessage()
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
            $scheme = Scheme::find($id);

            if (!$scheme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }

            // Check if scheme_name already exists (excluding current scheme)
            if ($request->has('scheme_name')) {
                $existingScheme = Scheme::where('scheme_name', $request->scheme_name)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingScheme) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Scheme with this name already exists',
                        'errors' => ['scheme_name' => ['The scheme name has already been taken.']]
                    ], 422);
                }
            }

            $validator = Validator::make($request->all(), [
                'scheme_name' => 'sometimes|required|string|max:200',
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
            if ($request->has('scheme_name')) {
                $updateData['scheme_name'] = $request->scheme_name;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $scheme->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Scheme updated successfully',
                'data' => $scheme
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update scheme: ' . $e->getMessage()
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
            $scheme = Scheme::find($id);

            if (!$scheme) {
                return response()->json([
                    'success' => false,
                    'message' => 'Scheme not found'
                ], 404);
            }

            // Check if scheme is being used in bridge_employee table
            $hasEmployees = DB::table('bcmis2.bridge_employee')
                ->where('scheme_id', $id)
                ->exists();

            if ($hasEmployees) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete scheme. It has associated employees.'
                ], 422);
            }

            $scheme->delete();

            return response()->json([
                'success' => true,
                'message' => 'Scheme deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete scheme: ' . $e->getMessage()
            ], 500);
        }
    }
}

