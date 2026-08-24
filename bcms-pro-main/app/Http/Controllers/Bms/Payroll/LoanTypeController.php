<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\LoanType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoanTypeController extends Controller
{
    public function ListLoanTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $hasInterest = $request->get('has_interest');
            $interestMethod = $request->get('interest_calculation_method');
            $sortBy = $request->get('sort_by', 'loan_type_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.loan_type as lt')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'lt.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'lt.modified_by')
                ->select(
                    'lt.loan_type_id',
                    'lt.loan_name',
                    'lt.loan_code',
                    'lt.has_interest',
                    'lt.interest_percentage',
                    'lt.interest_calculation_method',
                    'lt.minimum_loan_amount',
                    'lt.maximum_loan_amount',
                    'lt.minimum_repayment_months',
                    'lt.maximum_repayment_months',
                    'lt.is_active',
                    'lt.priority',
                    'lt.contract_type',
                    'lt.department_section',
                    'lt.job_title_position',
                    'lt.start_date',
                    'lt.end_date',
                    'lt.created_at',
                    'lt.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('lt.loan_name', 'like', "%{$search}%")
                        ->orWhere('lt.loan_code', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('lt.is_active', $status);
            }

            if ($hasInterest !== null) {
                $query->where('lt.has_interest', $hasInterest);
            }

            if ($interestMethod !== null) {
                $query->where('lt.interest_calculation_method', $interestMethod);
            }

            $query->orderBy('lt.' . $sortBy, $sortOrder);

            $loanTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Loan types retrieved successfully',
                'data' => [
                    $loanTypes->items(),
                    'pagination' => [
                        'current_page' => $loanTypes->currentPage(),
                        'last_page' => $loanTypes->lastPage(),
                        'per_page' => $loanTypes->perPage(),
                        'total' => $loanTypes->total(),
                        'from' => $loanTypes->firstItem(),
                        'to' => $loanTypes->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan types: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveLoanTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = LoanType::query()
                ->where('is_active', true)
                ->select([
                    'loan_type_id',
                    'loan_name',
                    'loan_code',
                    'has_interest',
                    'interest_percentage',
                    'interest_calculation_method',
                    'minimum_loan_amount',
                    'maximum_loan_amount',
                    'minimum_repayment_months',
                    'maximum_repayment_months',
                    'priority',
                ]);

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('loan_name', 'like', $term)
                        ->orWhere('loan_code', 'like', $term);
                });
            }

            $items = $query
                ->orderBy('priority')
                ->orderBy('loan_name')
                ->get();

            $options = $items->map(function ($item) {
                return [
                    'value' => $item->loan_type_id,
                    'label' => $item->loan_code
                        ? "{$item->loan_name} ({$item->loan_code})"
                        : $item->loan_name,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $options,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load loan types',
            ], 500);
        }
    }

    public function createLoanType(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'loan_name' => 'required|string|max:255',
                'loan_code' => 'required|string|max:50',
                'has_interest' => 'sometimes|boolean',
                'interest_percentage' => 'nullable|numeric|min:0|max:100',
                'interest_calculation_method' => 'nullable|in:flat,reducing_balance',
                'minimum_loan_amount' => 'nullable|numeric|min:0',
                'maximum_loan_amount' => 'nullable|numeric|min:0',
                'minimum_repayment_months' => 'nullable|integer|min:1',
                'maximum_repayment_months' => 'nullable|integer|min:1',
                'is_active' => 'required|boolean',
                'priority' => 'required|min:0',
                'contract_type' => 'nullable|integer',
                'department_section' => 'nullable|integer',
                'job_title_position' => 'nullable',
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

            $loanType = LoanType::create([
                'loan_name' => $request->loan_name,
                'loan_code' => $request->loan_code,
                'has_interest' => $request->boolean('has_interest'),
                'interest_percentage' => $request->input('interest_percentage'),
                'interest_calculation_method' => $request->input('interest_calculation_method'),
                'minimum_loan_amount' => $request->input('minimum_loan_amount'),
                'maximum_loan_amount' => $request->input('maximum_loan_amount'),
                'minimum_repayment_months' => $request->input('minimum_repayment_months'),
                'maximum_repayment_months' => $request->input('maximum_repayment_months'),
                'is_active' => $request->boolean('is_active'),
                'priority' => $request->input('priority'),
                'contract_type' => $request->contract_type,
                'department_section' => $request->department_section,
                'job_title_position' => $request->job_title_position,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => auth()->user()->pf_number,
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Loan type created successfully',
                'data' => $loanType,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create loan type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showLoanType($id): JsonResponse
    {
        try {
            $loanType = LoanType::find($id);

            if (!$loanType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan type not found',
                ], 404);
            }

            $loanTypeData = DB::table('bcmis2.loan_type as lt')
                ->leftJoin('bcmis.auth_user as creator', 'creator.pf_number', '=', 'lt.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.pf_number', '=', 'lt.modified_by')
                ->where('lt.loan_type_id', $id)
                ->select(
                    'lt.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Loan type retrieved successfully',
                'data' => $loanTypeData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve loan type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateLoanType(Request $request, $id): JsonResponse
    {
        try {
            $loanType = LoanType::find($id);

            if (!$loanType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan type not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'loan_name' => 'sometimes|string',
                'loan_code' => 'sometimes|string',
                'has_interest' => 'sometimes|boolean',
                'interest_percentage' => 'sometimes|numeric',
                'interest_calculation_method' => 'sometimes|string|in:flat,reducing_balance',
                'minimum_loan_amount' => 'sometimes|numeric|min:0',
                'maximum_loan_amount' => 'sometimes|numeric|min:0',
                'minimum_repayment_months' => 'sometimes|integer|min:1',
                'maximum_repayment_months' => 'sometimes|integer',
                'is_active' => 'sometimes|boolean',
                'priority' => 'nullable|integer',
                'contract_type' => 'nullable',
                'department_section' => 'nullable',
                'job_title_position' => 'nullable',
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
                'loan_name',
                'loan_code',
                'interest_percentage',
                'interest_calculation_method',
                'minimum_loan_amount',
                'maximum_loan_amount',
                'minimum_repayment_months',
                'maximum_repayment_months',
                'contract_type',
                'department_section',
                'job_title_position',
                'start_date',
                'end_date',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if ($request->has('priority')) {
                $updateData['priority'] = (int) $request->input('priority');
            }

            if ($request->has('has_interest')) {
                $updateData['has_interest'] = $request->boolean('has_interest');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $loanType->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Loan type updated successfully',
                'data' => $loanType,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loan type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleLoanTypeStatus($id): JsonResponse
    {
        try {
            $loanType = LoanType::findOrFail($id);

            $loanType->is_active = !$loanType->is_active;
            $loanType->modified_by = auth()->user()->pf_number;
            $loanType->save();

            return response()->json([
                'success' => true,
                'message' => $loanType->is_active
                    ? 'Loan type activated'
                    : 'Loan type deactivated',
                'data' => [
                    'loan_type_id' => $loanType->loan_type_id,
                    'is_active' => $loanType->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyLoanType($id): JsonResponse
    {
        try {
            $loanType = LoanType::find($id);

            if (!$loanType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Loan type not found',
                ], 404);
            }

            $loanType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Loan type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete loan type: ' . $e->getMessage(),
            ], 500);
        }
    }
}
