<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\ArrearsReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArrearsReasonController extends Controller
{
    public function ListArrearsReasons(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $isTaxable = $request->get('is_taxable');
            $sortBy = $request->get('sort_by', 'arrears_reason_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.arrears_reasons as ar')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'ar.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'ar.modified_by')
                ->select(
                    'ar.arrears_reason_id',
                    'ar.reason_name',
                    'ar.reason_code',
                    'ar.is_taxable',
                    'ar.is_active',
                    'ar.start_date',
                    'ar.end_date',
                    'ar.created_at',
                    'ar.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ar.reason_name', 'like', "%{$search}%")
                        ->orWhere('ar.reason_code', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('ar.is_active', $status);
            }

            if ($isTaxable !== null) {
                $query->where('ar.is_taxable', $isTaxable);
            }

            $query->orderBy('ar.' . $sortBy, $sortOrder);

            $arrearsReasons = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Arrears reasons retrieved successfully',
                'data' => [
                    $arrearsReasons->items(),
                    'pagination' => [
                        'current_page' => $arrearsReasons->currentPage(),
                        'last_page' => $arrearsReasons->lastPage(),
                        'per_page' => $arrearsReasons->perPage(),
                        'total' => $arrearsReasons->total(),
                        'from' => $arrearsReasons->firstItem(),
                        'to' => $arrearsReasons->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve arrears reasons: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveArrearsReasons(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = ArrearsReason::query()
                ->where('is_active', true)
                ->select('*');

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('reason_name', 'like', $term)
                        ->orWhere('reason_code', 'like', $term);
                });
            }

            $items = $query
                ->orderBy('reason_name')
                ->get();


            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load arrears reasons',
            ], 500);
        }
    }

    public function createArrearsReason(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason_name' => 'required|string|max:255',
                'reason_code' => 'required|string|max:50',
                'is_taxable' => 'required|boolean',
                'is_active' => 'required|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $arrearsReason = ArrearsReason::create([
                'reason_name' => $request->reason_name,
                'reason_code' => $request->reason_code,
                'is_taxable' => $request->boolean('is_taxable'),
                'is_active' => $request->boolean('is_active'),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => auth()->user()->pf_number,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Arrears reason created successfully',
                'data' => $arrearsReason,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create arrears reason: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showArrearsReason($id): JsonResponse
    {
        try {
            $arrearsReason = ArrearsReason::find($id);

            if (!$arrearsReason) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrears reason not found',
                ], 404);
            }

            $arrearsReasonData = DB::table('bcmis2.arrears_reasons as ar')
                ->leftJoin('bcmis.auth_user as creator', 'creator.pf_number', '=', 'ar.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.pf_number', '=', 'ar.modified_by')
                ->where('ar.arrears_reason_id', $id)
                ->select(
                    'ar.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Arrears reason retrieved successfully',
                'data' => $arrearsReasonData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve arrears reason: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateArrearsReason(Request $request, $id): JsonResponse
    {
        try {
            $arrearsReason = ArrearsReason::find($id);

            if (!$arrearsReason) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrears reason not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'reason_name' => 'sometimes|string|max:255',
                'reason_code' => 'sometimes|string|max:50',
                'is_taxable' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $updateData = [];
            foreach ([
                'reason_name',
                'reason_code',
                'start_date',
                'end_date',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if ($request->has('is_taxable')) {
                $updateData['is_taxable'] = $request->boolean('is_taxable');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $arrearsReason->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Arrears reason updated successfully',
                'data' => $arrearsReason,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update arrears reason: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleArrearsReasonStatus($id): JsonResponse
    {
        try {
            $arrearsReason = ArrearsReason::findOrFail($id);

            $arrearsReason->is_active = !$arrearsReason->is_active;
            $arrearsReason->modified_by = auth()->user()->pf_number;
            $arrearsReason->save();

            return response()->json([
                'success' => true,
                'message' => $arrearsReason->is_active
                    ? 'Arrears reason activated'
                    : 'Arrears reason deactivated',
                'data' => [
                    'arrears_reason_id' => $arrearsReason->arrears_reason_id,
                    'is_active' => $arrearsReason->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyArrearsReason($id): JsonResponse
    {
        try {
            $arrearsReason = ArrearsReason::find($id);

            if (!$arrearsReason) {
                return response()->json([
                    'success' => false,
                    'message' => 'Arrears reason not found',
                ], 404);
            }

            $arrearsReason->delete();

            return response()->json([
                'success' => true,
                'message' => 'Arrears reason deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete arrears reason: ' . $e->getMessage(),
            ], 500);
        }
    }
}

