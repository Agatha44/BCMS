<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\DeductionType;
use App\Services\Payroll\MandatoryDeductionProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeductionTypeController extends Controller
{
    public function ListDeductionTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $isBeforeTax = $request->get('is_before_tax');
            $calculationType = $request->get('calculation_type');
            $sortBy = $request->get('sort_by', 'deduction_type_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.deduction_type as dt')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'dt.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'dt.modified_by')
                ->select(
                    'dt.deduction_type_id',
                    'dt.deduction_name',
                    'dt.deduction_code',
                    'dt.calculation_type',
                    'dt.calculation_value',
                    'dt.is_before_tax',
                    'dt.employee_contribution_percentage',
                    'dt.employer_contribution_percentage',
                    'dt.priority',
                    'dt.is_active',
                    'dt.is_mandatory',
                    'dt.contract_type',
                    'dt.department_section',
                    'dt.job_title_position',
                    'dt.start_date',
                    'dt.end_date',
                    'dt.created_at',
                    'dt.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('dt.deduction_name', 'like', "%{$search}%")
                        ->orWhere('dt.deduction_code', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('dt.is_active', $status);
            }

            if ($isBeforeTax !== null) {
                $query->where('dt.is_before_tax', $isBeforeTax);
            }

            if ($calculationType !== null) {
                $query->where('dt.calculation_type', $calculationType);
            }

            $query->orderBy('dt.' . $sortBy, $sortOrder);

            $deductionTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Deduction types retrieved successfully',
                'data' => [
                    $deductionTypes->items(),
                    'pagination' => [
                        'current_page' => $deductionTypes->currentPage(),
                        'last_page' => $deductionTypes->lastPage(),
                        'per_page' => $deductionTypes->perPage(),
                        'total' => $deductionTypes->total(),
                        'from' => $deductionTypes->firstItem(),
                        'to' => $deductionTypes->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deduction types: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveDeductionTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = DeductionType::query()
                ->where('is_active', true)
                ->select('*');

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('deduction_name', 'like', $term)
                        ->orWhere('deduction_code', 'like', $term);
                });
            }

            $items = $query
                ->orderBy('deduction_name')
                ->limit(100)
                ->get();


            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load deduction types',
            ], 500);
        }
    }

    public function createDeductionType(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'deduction_name' => 'required|string|max:255',
                'deduction_code' => 'required|string|max:50',
                'calculation_type' => 'required|in:fixed,percentage',
                'calculation_value' => 'nullable|numeric|min:0',
                'is_before_tax' => 'sometimes|boolean',
                'employee_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'employer_contribution_percentage' => 'nullable|numeric|min:0|max:100',
                'priority' => 'sometimes|integer|min:0',
                'is_active' => 'sometimes|boolean',
                'is_mandatory' => 'sometimes|boolean',
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

            $deductionType = DeductionType::create([
                'deduction_name' => $request->deduction_name,
                'deduction_code' => $request->deduction_code,
                'calculation_type' => $request->calculation_type,
                'calculation_value' => $request->input('calculation_value'),
                'is_before_tax' => $request->boolean('is_before_tax'),
                'employee_contribution_percentage' => $request->input('employee_contribution_percentage'),
                'employer_contribution_percentage' => $request->input('employer_contribution_percentage'),
                'priority' => $request->input('priority', 0),
                'is_active' => $request->boolean('is_active', true),
                'is_mandatory' => $request->boolean('is_mandatory'),
                'contract_type' => $request->contract_type,
                'department_section' => $request->department_section,
                'job_title_position' => $request->job_title_position,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'created_by' => auth()->user()->pf_number,
                'created_at' => now(),
            ]);

            if ($deductionType->is_mandatory) {
                app(MandatoryDeductionProvisioner::class)->syncForAllActiveEmployees();
            }

            return response()->json([
                'success' => true,
                'message' => 'Deduction type created successfully',
                'data' => $deductionType,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create deduction type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showDeductionType($id): JsonResponse
    {
        try {
            $deductionType = DeductionType::find($id);

            if (!$deductionType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction type not found',
                ], 404);
            }

            $deductionTypeData = DB::table('bcmis2.deduction_type as dt')
                ->leftJoin('bcmis.auth_user as creator', 'creator.pf_number', '=', 'dt.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.pf_number', '=', 'dt.modified_by')
                ->where('dt.deduction_type_id', $id)
                ->select(
                    'dt.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Deduction type retrieved successfully',
                'data' => $deductionTypeData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deduction type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateDeductionType(Request $request, $id): JsonResponse
    {
        try {
            $deductionType = DeductionType::find($id);

            if (!$deductionType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction type not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'deduction_name' => 'sometimes|string|max:255',
                'deduction_code' => 'sometimes|string|max:50',
                'calculation_type' => 'sometimes|in:fixed,percentage',
                'calculation_value' => 'sometimes|numeric|min:0',
                'is_before_tax' => 'sometimes|boolean',
                'employee_contribution_percentage' => 'sometimes|numeric|min:0|max:100',
                'employer_contribution_percentage' => 'sometimes|numeric|min:0|max:100',
                'priority' => 'nullable|integer',
                'is_active' => 'sometimes|boolean',
                'is_mandatory' => 'sometimes|boolean',
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

            $rateFields = [
                'employee_contribution_percentage',
                'employer_contribution_percentage',
                'calculation_type',
                'calculation_value',
            ];
            $ratesChanged = false;
            foreach ($rateFields as $field) {
                if ($request->has($field) && (string) $request->input($field) !== (string) $deductionType->{$field}) {
                    $ratesChanged = true;
                    break;
                }
            }

            $wasMandatory = (bool) $deductionType->is_mandatory;

            $updateData = [];
            foreach ([
                'deduction_name',
                'deduction_code',
                'calculation_type',
                'calculation_value',
                'employee_contribution_percentage',
                'employer_contribution_percentage',
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

            if ($request->has('is_before_tax')) {
                $updateData['is_before_tax'] = $request->boolean('is_before_tax');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            if ($request->has('is_mandatory')) {
                $updateData['is_mandatory'] = $request->boolean('is_mandatory');
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $deductionType->update($updateData);
            $deductionType->refresh();

            $provisioner = app(MandatoryDeductionProvisioner::class);

            if ($request->has('is_mandatory') && $deductionType->is_mandatory && ! $wasMandatory) {
                $provisioner->syncForAllActiveEmployees();
            }

            if ($ratesChanged) {
                $provisioner->recalculateForDeductionType((int) $deductionType->deduction_type_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Deduction type updated successfully',
                'data' => $deductionType,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update deduction type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Flip is_active, or set it explicitly with JSON body: { "is_active": true|false }.
     */
    public function toggleDeductionTypeStatus($id): JsonResponse
    {
        try {
            $deductionType = DeductionType::findOrFail($id);

            $deductionType->is_active = !$deductionType->is_active;
            $deductionType->modified_by = auth()->user()->pf_number;
            $deductionType->save();

            return response()->json([
                'success' => true,
                'message' => $deductionType->is_active
                    ? 'Deduction type activated'
                    : 'Deduction type deactivated',
                'data' => [
                    'deduction_type_id' => $deductionType->deduction_type_id,
                    'is_active' => $deductionType->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyDeductionType($id): JsonResponse
    {
        try {
            $deductionType = DeductionType::find($id);

            if (!$deductionType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deduction type not found',
                ], 404);
            }

            $deductionType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Deduction type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete deduction type: ' . $e->getMessage(),
            ], 500);
        }
    }
}
