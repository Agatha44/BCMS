<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\Lane;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LaneController extends Controller
{
    /**
     * Get all lanes with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Lane::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('lane_no', 'like', "%{$search}%")
                      ->orWhere('camera_ip', 'like', "%{$search}%")
                      ->orWhere('reader_ip', 'like', "%{$search}%")
                      ->orWhere('mac_address', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== null) {
                $query->where('status', $request->status);
            }

            // Payment method filter
            if ($request->has('payment_method') && $request->payment_method !== null) {
                $query->where('payment_method', $request->payment_method);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'lane_no');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination with payment method relationship
            $perPage = $request->get('per_page', 15);
            $lanes = $query->with('paymentMethod')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Lanes retrieved successfully',
                'data' => [
                    'lanes' => $lanes->items(),
                    'pagination' => [
                        'current_page' => $lanes->currentPage(),
                        'last_page' => $lanes->lastPage(),
                        'per_page' => $lanes->perPage(),
                        'total' => $lanes->total(),
                        'from' => $lanes->firstItem(),
                        'to' => $lanes->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lanes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific lane
     */
    public function show($id): JsonResponse
    {
        try {
            $lane = Lane::with('paymentMethod')->find($id);

            if (!$lane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lane not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lane retrieved successfully',
                'data' => $lane
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lane: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new lane
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'lane_no' => 'required|string|max:50|unique:lane,lane_no',
                'camera_ip' => 'required|string|max:50',
                'reader_ip' => 'required|string|max:50',
                'com_port' => 'nullable|string|max:50',
                'payment_method' => 'required|integer|in:1,2,3,4',
                'reader_port' => 'nullable|integer',
                'mac_address' => 'nullable|string|max:50',
                'gate_ip' => 'nullable|string|max:100',
                'status' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if lane number already exists
            $existingLane = Lane::where('lane_no', $request->lane_no)->first();
            if ($existingLane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lane number already exists'
                ], 422);
            }

            $laneData = $request->all();
            $laneData['created_by'] = auth()->id();
            $laneData['updated_by'] = auth()->id();

            $lane = Lane::create($laneData);

            return response()->json([
                'success' => true,
                'message' => 'Lane created successfully',
                'data' => $lane
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create lane: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a lane
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $lane = Lane::find($id);

            if (!$lane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lane not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'lane_no' => 'required|string|max:50|unique:lane,lane_no,' . $id,
                'camera_ip' => 'required|string|max:50',
                'reader_ip' => 'required|string|max:50',
                'com_port' => 'nullable|string|max:50',
                'payment_method' => 'required|integer|in:1,2,3,4',
                'reader_port' => 'nullable|integer',
                'mac_address' => 'nullable|string|max:50',
                'gate_ip' => 'nullable|string|max:100',
                'status' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $laneData = $request->all();
            $laneData['updated_by'] = auth()->id();

            $lane->update($laneData);

            return response()->json([
                'success' => true,
                'message' => 'Lane updated successfully',
                'data' => $lane
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lane: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle lane status
     */
    public function toggleStatus($id): JsonResponse
    {
        try {
            $lane = Lane::find($id);

            if (!$lane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lane not found'
                ], 404);
            }

            $lane->status = !$lane->status;
            $lane->updated_by = auth()->id();
            $lane->save();

            return response()->json([
                'success' => true,
                'message' => 'Lane status updated successfully',
                'data' => $lane
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update lane status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a lane
     */
    public function destroy($id): JsonResponse
    {
        try {
            $lane = Lane::find($id);

            if (!$lane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lane not found'
                ], 404);
            }

            // Check if lane is being used in any transactions
            $hasTransactions = DB::table('toll_transaction')
                ->where('lane_id', $id)
                ->exists();

            if ($hasTransactions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete lane. It has associated transactions.'
                ], 422);
            }

            $lane->delete();

            return response()->json([
                'success' => true,
                'message' => 'Lane deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete lane: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $paymentMethods = PaymentMethod::active()
                ->orderBy('description')
                ->get(['id', 'description as name']);

            return response()->json([
                'success' => true,
                'message' => 'Payment methods retrieved successfully',
                'data' => $paymentMethods
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active lanes
     */
    public function getActiveLanes(): JsonResponse
    {
        try {
            $lanes = Lane::active()
                ->with('paymentMethod')
                ->orderBy('lane_no')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Active lanes retrieved successfully',
                'data' => $lanes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active lanes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual open gate
     */
    public function manualOpenGate(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'lane_id' => 'required|integer|exists:lane,id',
                'user_id' => 'required|integer',
                'reason' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Log the manual gate opening
            DB::table('open_gate')->insert([
                'lane_id' => $request->lane_id,
                'user_id' => $request->user_id,
                'reason' => $request->reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Manual gate opening logged successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to log manual gate opening: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get lanes for public access (POS terminals)
     */
    public function getPublicLanes(): JsonResponse
    {
        try {
            $lanes = Lane::select('id', 'lane_no', 'camera_ip', 'reader_ip', 'status', 'payment_method')
                ->orderBy('lane_no')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lanes retrieved successfully',
                'data' => $lanes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve lanes: ' . $e->getMessage()
            ], 500);
        }
    }
}
