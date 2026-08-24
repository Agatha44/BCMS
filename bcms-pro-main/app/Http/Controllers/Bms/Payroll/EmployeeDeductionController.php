<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Constants\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\EmployeeDeduction;
use App\Services\Payroll\MandatoryDeductionProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeDeductionController extends Controller
{
    public function getActiveEmployeeDeductions(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $deductionTypeId = $request->get('deduction_type_id');
            $isBeforeTax = $request->get('is_before_tax');
            $effectiveStartDate = $request->get('effective_start_date');
            $effectiveEndDate = $request->get('effective_end_date');

            $sortBy = $request->get('sort_by', 'employee_deduction_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_deduction as ed')
                ->leftJoin('bcmis2.deduction_type as dt', 'dt.deduction_type_id', '=', 'ed.deduction_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'ed.employee_national_id')
                ->where('ed.is_active', true)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'ed.*',
                    'dt.deduction_name as deduction_name',
                    'dt.deduction_code as deduction_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name")
                );

            if ($deductionTypeId !== null) {
                $query->where('ed.deduction_type_id', $deductionTypeId);
            }

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('ed.employee_national_id', 'like', $term)
                        ->orWhere('dt.deduction_name', 'like', $term)
                        ->orWhere('dt.deduction_code', 'like', $term)
                        ->orWhere(DB::raw("CONCAT(be.fname, ' ', be.sname)"), 'like', $term);
                });
            }

            if ($isBeforeTax !== null) {
                $query->where('ed.is_before_tax', $isBeforeTax);
            }

            if ($effectiveStartDate !== null) {
                $query->whereDate('ed.effective_start_date', '>=', $effectiveStartDate);
            }

            if ($effectiveEndDate !== null) {
                $query->whereDate('ed.effective_end_date', '<=', $effectiveEndDate);
            }

            $query->orderBy('ed.' . $sortBy, $sortOrder);

            $deductions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Active employee deductions retrieved successfully',
                'data' => [
                    $deductions->items(),
                    'pagination' => [
                        'current_page' => $deductions->currentPage(),
                        'last_page' => $deductions->lastPage(),
                        'per_page' => $deductions->perPage(),
                        'total' => $deductions->total(),
                        'from' => $deductions->firstItem(),
                        'to' => $deductions->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active employee deductions',
            ], 500);
        }
    }

    public function ListEmployeeDeductions(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search'); // employee national id
            $status = $request->get('status'); // is_active
            $deductionTypeId = $request->get('deduction_type_id');
            $isBeforeTax = $request->get('is_before_tax');
            $effectiveStartDate = $request->get('effective_start_date');
            $effectiveEndDate = $request->get('effective_end_date');

            $sortBy = $request->get('sort_by', 'employee_deduction_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_deduction as ed')
                ->leftJoin('bcmis2.deduction_type as dt', 'dt.deduction_type_id', '=', 'ed.deduction_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'ed.employee_national_id')
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'ed.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'ed.modified_by')
                ->select(
                    'ed.*',
                    'dt.deduction_name as deduction_name',
                    'dt.deduction_code as deduction_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by_name"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by_name")
                );

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('ed.employee_national_id', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('ed.is_active', $status);
            }

            if ($deductionTypeId !== null) {
                $query->where('ed.deduction_type_id', $deductionTypeId);
            }

            if ($isBeforeTax !== null) {
                $query->where('ed.is_before_tax', $isBeforeTax);
            }

            if ($effectiveStartDate !== null) {
                $query->whereDate('ed.effective_start_date', '>=', $effectiveStartDate);
            }

            if ($effectiveEndDate !== null) {
                $query->whereDate('ed.effective_end_date', '<=', $effectiveEndDate);
            }

            $query->orderBy('ed.' . $sortBy, $sortOrder);

            $deductions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employee deductions retrieved successfully',
                'data' => [
                    $deductions->items(),
                    'pagination' => [
                        'current_page' => $deductions->currentPage(),
                        'last_page' => $deductions->lastPage(),
                        'per_page' => $deductions->perPage(),
                        'total' => $deductions->total(),
                        'from' => $deductions->firstItem(),
                        'to' => $deductions->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee deductions: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createEmployeeDeduction(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'required|string|max:50',
                'deduction_type_id' => 'required|integer',
                'total_deduction_amount' => 'required|numeric|min:0',
                'employee_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'employee_contribution_amount' => 'nullable|numeric|min:0',
                'employer_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'employer_contribution_amount' => 'nullable|numeric|min:0',
                'is_before_tax' => 'sometimes|boolean',
                'taxable_amount' => 'nullable|numeric|min:0',
                'tax_free_amount' => 'nullable|numeric|min:0',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date',
                'is_active' => 'sometimes|boolean',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $deduction = EmployeeDeduction::create([
                'employee_national_id' => $request->employee_national_id,
                'deduction_type_id' => $request->deduction_type_id,
                'total_deduction_amount' => $request->total_deduction_amount,
                'employee_contribution_percentage' => $request->input('employee_contribution_percentage'),
                'employee_contribution_amount' => $request->input('employee_contribution_amount'),
                'employer_contribution_percentage' => $request->input('employer_contribution_percentage'),
                'employer_contribution_amount' => $request->input('employer_contribution_amount'),
                'is_before_tax' => $request->boolean('is_before_tax'),
                'taxable_amount' => $request->input('taxable_amount'),
                'tax_free_amount' => $request->input('tax_free_amount'),
                'effective_start_date' => $request->input('effective_start_date'),
                'effective_end_date' => $request->input('effective_end_date'),
                'is_active' => $request->boolean('is_active', true),
                'notes' => $request->input('notes'),
                'created_by' => auth()->user()->pf_number,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee deduction created successfully',
                'data' => $deduction,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee deduction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showEmployeeDeduction($id): JsonResponse
    {
        try {
            $deduction = EmployeeDeduction::find($id);

            if (!$deduction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee deduction not found',
                ], 404);
            }

            $deductionData = DB::table('bcmis2.employee_deduction as ed')
                ->leftJoin('bcmis2.deduction_type as dt', 'dt.deduction_type_id', '=', 'ed.deduction_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'ed.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'ed.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'ed.modified_by')
                ->where('ed.employee_deduction_id', $id)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'ed.*',
                    'dt.deduction_name as deduction_name',
                    'dt.deduction_code as deduction_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Employee deduction retrieved successfully',
                'data' => $deductionData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee deduction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEmployeeDeduction(Request $request, $id): JsonResponse
    {
        try {
            $deduction = EmployeeDeduction::find($id);

            if (!$deduction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee deduction not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'sometimes|string|max:50',
                'deduction_type_id' => 'sometimes|integer',
                'total_deduction_amount' => 'sometimes|numeric|min:0',
                'employee_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'employee_contribution_amount' => 'nullable|numeric|min:0',
                'employer_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'employer_contribution_amount' => 'nullable|numeric|min:0',
                'is_before_tax' => 'sometimes|boolean',
                'taxable_amount' => 'nullable|numeric|min:0',
                'tax_free_amount' => 'nullable|numeric|min:0',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
                'is_active' => 'sometimes|boolean',
                'notes' => 'nullable|string|max:500',
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
                'employee_national_id',
                'deduction_type_id',
                'total_deduction_amount',
                'employee_contribution_percentage',
                'employee_contribution_amount',
                'employer_contribution_percentage',
                'employer_contribution_amount',
                'taxable_amount',
                'tax_free_amount',
                'effective_start_date',
                'effective_end_date',
                'notes',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if ($request->has('is_before_tax')) {
                $updateData['is_before_tax'] = $request->boolean('is_before_tax');
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $deduction->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Employee deduction updated successfully',
                'data' => $deduction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee deduction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleEmployeeDeductionStatus($id): JsonResponse
    {
        try {
            $deduction = EmployeeDeduction::findOrFail($id);

            $deduction->is_active = !$deduction->is_active;
            $deduction->modified_by = auth()->user()->pf_number;
            $deduction->modified_at = now();
            $deduction->save();

            return response()->json([
                'success' => true,
                'message' => $deduction->is_active ? 'Employee deduction activated' : 'Employee deduction deactivated',
                'data' => [
                    'employee_deduction_id' => $deduction->employee_deduction_id,
                    'is_active' => $deduction->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyEmployeeDeduction($id): JsonResponse
    {
        try {
            $deduction = EmployeeDeduction::find($id);

            if (!$deduction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee deduction not found',
                ], 404);
            }

            $deduction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee deduction deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee deduction: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function syncMandatoryDeductions(Request $request): JsonResponse
    {
        try {
            $nationalId = $request->input('employee_national_id');
            $effectiveStartDate = $request->input('effective_start_date');
            $provisioner = app(MandatoryDeductionProvisioner::class);

            if ($nationalId) {
                $result = $provisioner->syncForEmployee($nationalId, $effectiveStartDate);

                return response()->json([
                    'success' => true,
                    'message' => 'Mandatory deductions synced for employee',
                    'data' => $result,
                ]);
            }

            $result = $provisioner->syncForAllActiveEmployees($effectiveStartDate);

            return response()->json([
                'success' => true,
                'message' => 'Mandatory deductions synced for all active employees',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync mandatory deductions: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function recalculateForEmployee(Request $request, string $nationalId): JsonResponse
    {
        try {
            $result = app(MandatoryDeductionProvisioner::class)->recalculateForEmployee($nationalId);

            return response()->json([
                'success' => true,
                'message' => 'Employee payroll assignments recalculated',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate employee assignments: ' . $e->getMessage(),
            ], 500);
        }
    }
}

