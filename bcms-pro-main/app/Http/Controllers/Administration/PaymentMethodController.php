<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentMethod::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where('description', 'like', "%{$search}%");
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'description');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $paymentMethods = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Payment methods retrieved successfully',
                'data' => [
                    'payment_methods' => $paymentMethods->items(),
                    'pagination' => [
                        'current_page' => $paymentMethods->currentPage(),
                        'last_page' => $paymentMethods->lastPage(),
                        'per_page' => $paymentMethods->perPage(),
                        'total' => $paymentMethods->total(),
                        'from' => $paymentMethods->firstItem(),
                        'to' => $paymentMethods->lastItem(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific payment method
     */
    public function show($id): JsonResponse
    {
        try {
            $paymentMethod = PaymentMethod::find($id);

            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment method retrieved successfully',
                'data' => $paymentMethod
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new payment method
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'description' => 'required|string|max:50|unique:payment_methods,description',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $paymentMethod = PaymentMethod::create([
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method created successfully',
                'data' => $paymentMethod
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a payment method
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $paymentMethod = PaymentMethod::find($id);

            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'description' => 'required|string|max:50|unique:payment_methods,description,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $paymentMethod->update([
                'description' => $request->description,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment method updated successfully',
                'data' => $paymentMethod
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a payment method
     */
    public function destroy($id): JsonResponse
    {
        try {
            $paymentMethod = PaymentMethod::find($id);

            if (!$paymentMethod) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not found'
                ], 404);
            }

            // Check if payment method is being used by any lanes
            $lanesCount = $paymentMethod->lanes()->count();
            if ($lanesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete payment method. It is being used by {$lanesCount} lane(s)."
                ], 422);
            }

            $paymentMethod->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment method deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment method: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all payment methods for dropdown
     */
    public function getForDropdown(): JsonResponse
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
}
