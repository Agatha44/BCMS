<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Constants\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\EmployeeBenefit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeBenefitController extends Controller
{
    public function ListEmployeeBenefits(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search'); // employee national id
            $status = $request->get('status'); // is_active
            $benefitTypeId = $request->get('benefit_type_id');
            $effectiveStartDate = $request->get('effective_start_date');
            $effectiveEndDate = $request->get('effective_end_date');

            $sortBy = $request->get('sort_by', 'employee_benefit_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_benefit as eb')
                ->leftJoin('bcmis2.benefit_type as bt', 'bt.benefit_type_id', '=', 'eb.benefit_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'eb.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'eb.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'eb.modified_by')
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'eb.*',
                    'bt.benefit_name as benefit_name',
                    'bt.benefit_code as benefit_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('eb.employee_national_id', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('eb.is_active', $status);
            }

            if ($benefitTypeId !== null) {
                $query->where('eb.benefit_type_id', $benefitTypeId);
            }

            if ($effectiveStartDate !== null) {
                $query->whereDate('eb.effective_start_date', '>=', $effectiveStartDate);
            }

            if ($effectiveEndDate !== null) {
                $query->whereDate('eb.effective_end_date', '<=', $effectiveEndDate);
            }

            $query->orderBy('eb.' . $sortBy, $sortOrder);

            $benefits = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employee benefits retrieved successfully',
                'data' => [
                    $benefits->items(),
                    'pagination' => [
                        'current_page' => $benefits->currentPage(),
                        'last_page' => $benefits->lastPage(),
                        'per_page' => $benefits->perPage(),
                        'total' => $benefits->total(),
                        'from' => $benefits->firstItem(),
                        'to' => $benefits->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee benefits: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveEmployeeBenefits(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $benefitTypeId = $request->get('benefit_type_id');
            $effectiveStartDate = $request->get('effective_start_date');
            $effectiveEndDate = $request->get('effective_end_date');

            $sortBy = $request->get('sort_by', 'employee_benefit_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_benefit as eb')
                ->leftJoin('bcmis2.benefit_type as bt', 'bt.benefit_type_id', '=', 'eb.benefit_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'eb.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'eb.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'eb.modified_by')
                ->where('eb.is_active', true)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'eb.*',
                    'bt.benefit_name as benefit_name',
                    'bt.benefit_code as benefit_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($benefitTypeId !== null) {
                $query->where('eb.benefit_type_id', $benefitTypeId);
            }

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('eb.employee_national_id', 'like', $term)
                        ->orWhere('bt.benefit_name', 'like', $term)
                        ->orWhere('bt.benefit_code', 'like', $term)
                        ->orWhere(DB::raw("CONCAT(be.fname, ' ', be.sname)"), 'like', $term);
                });
            }

            if ($effectiveStartDate !== null) {
                $query->whereDate('eb.effective_start_date', '>=', $effectiveStartDate);
            }

            if ($effectiveEndDate !== null) {
                $query->whereDate('eb.effective_end_date', '<=', $effectiveEndDate);
            }

            $query->orderBy('eb.' . $sortBy, $sortOrder);

            $benefits = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Active employee benefits retrieved successfully',
                'data' => [
                    $benefits->items(),
                    'pagination' => [
                        'current_page' => $benefits->currentPage(),
                        'last_page' => $benefits->lastPage(),
                        'per_page' => $benefits->perPage(),
                        'total' => $benefits->total(),
                        'from' => $benefits->firstItem(),
                        'to' => $benefits->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve active employee benefits',
            ], 500);
        }
    }

    public function createEmployeeBenefit(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'required|string|max:50',
                'benefit_type_id' => 'required|integer',
                'benefit_amount' => 'required|numeric|min:0',
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

            $benefit = EmployeeBenefit::create([
                'employee_national_id' => $request->employee_national_id,
                'benefit_type_id' => $request->benefit_type_id,
                'benefit_amount' => $request->benefit_amount,
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
                'message' => 'Employee benefit created successfully',
                'data' => $benefit,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee benefit: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showEmployeeBenefit($id): JsonResponse
    {
        try {
            $benefit = EmployeeBenefit::find($id);

            if (!$benefit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee benefit not found',
                ], 404);
            }

            $benefitData = DB::table('bcmis2.employee_benefit as eb')
                ->leftJoin('bcmis2.benefit_type as bt', 'bt.benefit_type_id', '=', 'eb.benefit_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'eb.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'eb.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'eb.modified_by')
                ->where('eb.employee_benefit_id', $id)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'eb.*',
                    'bt.benefit_name as benefit_name',
                    'bt.benefit_code as benefit_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Employee benefit retrieved successfully',
                'data' => $benefitData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee benefit: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEmployeeBenefit(Request $request, $id): JsonResponse
    {
        try {
            $benefit = EmployeeBenefit::find($id);

            if (!$benefit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee benefit not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'sometimes|string|max:50',
                'benefit_type_id' => 'sometimes|integer',
                'benefit_amount' => 'sometimes|numeric|min:0',
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

            $updateData = [];
            foreach ([
                'employee_national_id',
                'benefit_type_id',
                'benefit_amount',
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

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $benefit->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Employee benefit updated successfully',
                'data' => $benefit,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee benefit: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleEmployeeBenefitStatus($id): JsonResponse
    {
        try {
            $benefit = EmployeeBenefit::findOrFail($id);

            $benefit->is_active = !$benefit->is_active;
            $benefit->modified_by = auth()->user()->pf_number;
            $benefit->modified_at = now();
            $benefit->save();

            return response()->json([
                'success' => true,
                'message' => $benefit->is_active ? 'Employee benefit activated' : 'Employee benefit deactivated',
                'data' => [
                    'employee_benefit_id' => $benefit->employee_benefit_id,
                    'is_active' => $benefit->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyEmployeeBenefit($id): JsonResponse
    {
        try {
            $benefit = EmployeeBenefit::find($id);

            if (!$benefit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee benefit not found',
                ], 404);
            }

            $benefit->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee benefit deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee benefit: ' . $e->getMessage(),
            ], 500);
        }
    }
}

