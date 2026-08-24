<?php

namespace App\Http\Controllers\Bms;

use App\Http\Controllers\Controller;
use App\Models\Bms\Bank;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
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
            $sortBy = $request->get('sort_by', 'bank_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.bank as bank')
                ->leftJoin('bcmis.auth_user as cr', 'cr.id', '=', 'bank.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.id', '=', 'bank.modified_by')
                ->select(
                    'bank.bank_id',
                    'bank.bank_name',
                    'bank.short_name',
                    'bank.sort_code',
                    'bank.bank_code',
                    'bank.bi_code',
                    'bank.is_active',
                    'bank.erp_bc',
                    'bank.erp_br',
                    'bank.citi_code',
                    'bank.swift_code',
                    'bank.created_at',
                    'bank.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            // Search functionality
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('bank.bank_name', 'like', "%{$search}%")
                      ->orWhere('bank.short_name', 'like', "%{$search}%")
                      ->orWhere('bank.bank_code', 'like', "%{$search}%")
                      ->orWhere('bank.bi_code', 'like', "%{$search}%")
                      ->orWhere('bank.swift_code', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($status !== null) {
                $query->where('bank.is_active', $status);
            }

            // Sorting
            $query->orderBy('bank.' . $sortBy, $sortOrder);

            // Pagination
            $banks = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Banks retrieved successfully',
                'data' => [
                    'banks' => $banks->items(),
                    'pagination' => [
                        'current_page' => $banks->currentPage(),
                        'last_page' => $banks->lastPage(),
                        'per_page' => $banks->perPage(),
                        'total' => $banks->total(),
                        'from' => $banks->firstItem(),
                        'to' => $banks->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve banks: ' . $e->getMessage()
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
                'bank_name' => 'required|string|max:255',
                'short_name' => 'required|string|max:100',
                'sort_code' => 'nullable|string|max:50',
                'bank_code' => 'required|string|max:50',
                'bi_code' => 'required|string|max:50',
                'is_active' => 'sometimes|boolean',
                'erp_bc' => 'nullable|string|max:50',
                'erp_br' => 'nullable|string|max:50',
                'citi_code' => 'nullable|string|max:50',
                'swift_code' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bank = Bank::create([
                'bank_name' => $request->bank_name,
                'short_name' => $request->short_name,
                'sort_code' => $request->sort_code,
                'bank_code' => $request->bank_code,
                'bi_code' => $request->bi_code,
                'is_active' => $request->get('is_active', true),
                'erp_bc' => $request->erp_bc,
                'erp_br' => $request->erp_br,
                'citi_code' => $request->citi_code,
                'swift_code' => $request->swift_code,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'modified_by' => auth()->id(),
                'modified_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank created successfully',
                'data' => $bank
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $bankId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($bankId): JsonResponse
    {
        try {
            $bank = Bank::find($bankId);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $bankData = DB::table('bcmis2.bank as bank')
                ->leftJoin('bcmis.auth_user as creator', 'creator.id', '=', 'bank.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.id', '=', 'bank.modified_by')
                ->where('bank.bank_id', $bankId)
                ->select(
                    'bank.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by_name"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by_name")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Bank retrieved successfully',
                'data' => $bankData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bank: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active banks
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive(): JsonResponse
    {
        try {
            $banks = Bank::active()
                ->orderBy('bank_name', 'asc')
                ->get(['bank_id', 'bank_name', 'short_name', 'bank_code', 'is_active']);

            return response()->json([
                'success' => true,
                'message' => 'Active banks retrieved successfully',
                'data' => $banks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active banks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $bankId
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $bankId): JsonResponse
    {
        try {
            $bank = Bank::find($bankId);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'bank_name' => 'sometimes|required|string|max:255',
                'short_name' => 'sometimes|required|string|max:100',
                'sort_code' => 'nullable|string|max:50',
                'bank_code' => 'sometimes|required|string|max:50',
                'bi_code' => 'sometimes|required|string|max:50',
                'is_active' => 'sometimes|required|boolean',
                'erp_bc' => 'nullable|string|max:50',
                'erp_br' => 'nullable|string|max:50',
                'citi_code' => 'nullable|string|max:50',
                'swift_code' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('bank_name')) {
                $updateData['bank_name'] = $request->bank_name;
            }
            if ($request->has('short_name')) {
                $updateData['short_name'] = $request->short_name;
            }
            if ($request->has('sort_code')) {
                $updateData['sort_code'] = $request->sort_code;
            }
            if ($request->has('bank_code')) {
                $updateData['bank_code'] = $request->bank_code;
            }
            if ($request->has('bi_code')) {
                $updateData['bi_code'] = $request->bi_code;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }
            if ($request->has('erp_bc')) {
                $updateData['erp_bc'] = $request->erp_bc;
            }
            if ($request->has('erp_br')) {
                $updateData['erp_br'] = $request->erp_br;
            }
            if ($request->has('citi_code')) {
                $updateData['citi_code'] = $request->citi_code;
            }
            if ($request->has('swift_code')) {
                $updateData['swift_code'] = $request->swift_code;
            }
            $updateData['modified_by'] = auth()->id();
            $updateData['modified_at'] = now();

            $bank->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Bank updated successfully',
                'data' => $bank
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $bankId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($bankId): JsonResponse
    {
        try {
            $bank = Bank::find($bankId);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $bank->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bank deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle bank status
     *
     * @param  int  $bankId
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleStatus($bankId): JsonResponse
    {
        try {
            $bank = Bank::find($bankId);

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank not found'
                ], 404);
            }

            $bank->is_active = !$bank->is_active;
            $bank->modified_by = auth()->id();
            $bank->modified_at = now();
            $bank->save();

            return response()->json([
                'success' => true,
                'message' => 'Bank status updated successfully',
                'data' => $bank
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank status: ' . $e->getMessage()
            ], 500);
        }
    }
}

