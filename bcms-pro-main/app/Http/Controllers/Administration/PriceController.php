<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListAudit;
use App\Models\BodyType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PriceController extends Controller
{
    /**
     * Get all prices with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PriceList::with('bodyType');

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->whereHas('bodyType', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== null) {
                $query->where('status', $request->status);
            }

            // Body type filter
            if ($request->has('body_type_id') && $request->body_type_id !== null) {
                $query->where('body_type_id', $request->body_type_id);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $prices = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Prices retrieved successfully',
                'data' => [
                    'prices' => $prices->items(),
                    'pagination' => [
                        'current_page' => $prices->currentPage(),
                        'last_page' => $prices->lastPage(),
                        'per_page' => $prices->perPage(),
                        'total' => $prices->total(),
                        'from' => $prices->firstItem(),
                        'to' => $prices->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve prices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paginated change history for a price list row (from `price_list_audit`).
     */
    public function audits(Request $request, $id): JsonResponse
    {
        try {
            $price = PriceList::find($id);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found',
                ], 404);
            }

            $perPage = min(max((int) $request->get('per_page', 15), 1), 100);
            $query = PriceListAudit::query()
                ->where('price_list_id', $id)
                ->with(['updatedBy:id,name,email'])
                ->orderByDesc('created_at');

            $audits = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Price audit history retrieved successfully',
                'data' => [
                    'audits' => $audits->items(),
                    'pagination' => [
                        'current_page' => $audits->currentPage(),
                        'last_page' => $audits->lastPage(),
                        'per_page' => $audits->perPage(),
                        'total' => $audits->total(),
                        'from' => $audits->firstItem(),
                        'to' => $audits->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve price audits: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific price
     */
    public function show($id): JsonResponse
    {
        try {
            $price = PriceList::with('bodyType')->find($id);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Price retrieved successfully',
                'data' => $price
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new price
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'required|integer|exists:body_type,id',
                'amount' => 'required|numeric|min:0',
                'daily_bundle_amount' => 'required|numeric|min:0',
                'weekly_bundle_amount' => 'required|numeric|min:0',
                'monthly_bundle_amount' => 'required|numeric|min:0',
                'status' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if price already exists for this body type
            $existingPrice = PriceList::where('body_type_id', $request->body_type_id)->first();
            if ($existingPrice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price already exists for this body type'
                ], 422);
            }

            $price = PriceList::create([
                'body_type_id' => $request->body_type_id,
                'amount' => $request->amount,
                'daily_bundle_amount' => $request->daily_bundle_amount,
                'weekly_bundle_amount' => $request->weekly_bundle_amount,
                'monthly_bundle_amount' => $request->monthly_bundle_amount,
                'status' => $request->get('status', true),
                'created_by' => auth()->id()
            ]);

            // Load the body type relationship
            $price->load('bodyType');

            return response()->json([
                'success' => true,
                'message' => 'Price created successfully',
                'data' => $price
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a price (supports partial updates including status)
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $price = PriceList::find($id);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 404);
            }

            // Validation rules - all fields are optional for partial updates
            $validator = Validator::make($request->all(), [
                'body_type_id' => 'sometimes|required|integer|exists:body_type,id',
                'amount' => 'sometimes|required|numeric|min:0',
                'daily_bundle_amount' => 'sometimes|required|numeric|min:0',
                'weekly_bundle_amount' => 'sometimes|required|numeric|min:0',
                'monthly_bundle_amount' => 'sometimes|required|numeric|min:0',
                'status' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if body_type_id is being updated and if it already exists (excluding current price)
            if ($request->has('body_type_id') && $request->body_type_id != $price->body_type_id) {
                $existingPrice = PriceList::where('body_type_id', $request->body_type_id)
                    ->where('id', '!=', $id)
                    ->first();
                if ($existingPrice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Price already exists for this body type'
                    ], 422);
                }
            }

            // Store old values for audit logging
            $oldValues = [
                'body_type_id' => $price->body_type_id,
                'amount' => $price->amount,
                'daily_bundle_amount' => $price->daily_bundle_amount,
                'weekly_bundle_amount' => $price->weekly_bundle_amount,
                'monthly_bundle_amount' => $price->monthly_bundle_amount,
                'status' => $price->status
            ];

            // Build update array with only provided fields
            $updateData = [];
            
            if ($request->has('body_type_id')) {
                $updateData['body_type_id'] = $request->body_type_id;
            }
            
            if ($request->has('amount')) {
                $updateData['amount'] = $request->amount;
            }
            
            if ($request->has('daily_bundle_amount')) {
                $updateData['daily_bundle_amount'] = $request->daily_bundle_amount;
            }
            
            if ($request->has('weekly_bundle_amount')) {
                $updateData['weekly_bundle_amount'] = $request->weekly_bundle_amount;
            }
            
            if ($request->has('monthly_bundle_amount')) {
                $updateData['monthly_bundle_amount'] = $request->monthly_bundle_amount;
            }
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }
            
            // Always update the updated_by field
            $updateData['updated_by'] = auth()->id();

            // Update only if there are fields to update
            if (!empty($updateData)) {
                $price->update($updateData);
                
                // Refresh the model to get latest data
                $price->refresh();
                
                // Log audit trail
                PriceListAudit::create([
                    'price_list_id' => $price->id,
                    'previous_body_type_id' => $oldValues['body_type_id'],
                    'new_body_type_id' => $price->body_type_id,
                    'previous_amount' => $oldValues['amount'],
                    'new_amount' => $price->amount,
                    'previous_daily_bundle_amount' => $oldValues['daily_bundle_amount'],
                    'new_daily_bundle_amount' => $price->daily_bundle_amount,
                    'previous_weekly_bundle_amount' => $oldValues['weekly_bundle_amount'],
                    'new_weekly_bundle_amount' => $price->weekly_bundle_amount,
                    'previous_monthly_bundle_amount' => $oldValues['monthly_bundle_amount'],
                    'new_monthly_bundle_amount' => $price->monthly_bundle_amount,
                    'previous_status' => $oldValues['status'],
                    'new_status' => $price->status,
                    'action' => PriceListAudit::ACTION_UPDATED,
                    'updated_by' => auth()->id()
                ]);
            }
            
            // Load the body type relationship
            $price->load('bodyType');

            return response()->json([
                'success' => true,
                'message' => 'Price updated successfully',
                'data' => $price
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle price status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $price = PriceList::find($id);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 404);
            }

            $price->update([
                'status' => !$price->status,
                'updated_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price status updated successfully',
                'data' => $price
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update price status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a price
     */
    public function destroy($id): JsonResponse
    {
        try {
            $price = PriceList::find($id);

            if (!$price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price not found'
                ], 404);
            }

            // Check if price is being used in transactions
            $transactionCount = DB::table('toll_transaction')
                ->where('price_list_id', $id)
                ->count();

            if ($transactionCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete price. It is being used by {$transactionCount} transaction(s)."
                ], 422);
            }

            $price->delete();

            return response()->json([
                'success' => true,
                'message' => 'Price deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete price: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active body types for dropdown
     */
    public function getActiveBodyTypes(): JsonResponse
    {
        try {
            $bodyTypes = BodyType::active()
                ->orderBy('name')
                ->get(['id', 'name', 'description']);

            return response()->json([
                'success' => true,
                'message' => 'Active body types retrieved successfully',
                'data' => $bodyTypes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve body types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active prices
     */
    public function getActivePrices(): JsonResponse
    {
        try {
            $prices = PriceList::active()
                ->with('bodyType')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Active prices retrieved successfully',
                'data' => $prices
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active prices: ' . $e->getMessage()
            ], 500);
        }
    }
}
