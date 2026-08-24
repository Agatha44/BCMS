<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\BenefitType;
use App\Services\Payroll\MandatoryDeductionProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BenefitTypeController extends Controller
{
    public function ListBenefitTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $isTaxable = $request->get('is_taxable');
            $calculationType = $request->get('calculation_type');
            $sortBy = $request->get('sort_by', 'benefit_type_id');
            $sortOrder = $request->get('sort_order', 'asc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.benefit_type as bt')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'bt.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'bt.modified_by')
                ->select(
                    'bt.benefit_type_id',
                    'bt.benefit_name',
                    'bt.benefit_code',
                    'bt.calculation_type',
                    'bt.calculation_value',
                    'bt.is_taxable',
                    'bt.is_active',
                    'bt.contract_type',
                    'bt.department_section',
                    'bt.job_title_position',
                    'bt.start_date',
                    'bt.end_date',
                    'bt.created_at',
                    'bt.modified_at',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('bt.benefit_name', 'like', "%{$search}%")
                        ->orWhere('bt.benefit_code', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('bt.is_active', $status);
            }

            if ($isTaxable !== null) {
                $query->where('bt.is_taxable', $isTaxable);
            }

            if ($calculationType !== null) {
                $query->where('bt.calculation_type', $calculationType);
            }

            $query->orderBy('bt.' . $sortBy, $sortOrder);

            $benefitTypes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Benefit types retrieved successfully',
                'data' => [
                    $benefitTypes->items(),
                    'pagination' => [
                        'current_page' => $benefitTypes->currentPage(),
                        'last_page' => $benefitTypes->lastPage(),
                        'per_page' => $benefitTypes->perPage(),
                        'total' => $benefitTypes->total(),
                        'from' => $benefitTypes->firstItem(),
                        'to' => $benefitTypes->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve benefit types: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getActiveBenefitTypes(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');

            $query = BenefitType::query()
                ->where('is_active', true)
                ->select('*');

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('benefit_name', 'like', $term)
                    ->orWhere('benefit_code', 'like', $term);
                });
            }

            $items = $query
                ->orderBy('benefit_name')
                ->limit(100)
                ->get();
                

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load benefit types',
            ], 500);
        }
    }


    public function createBenefitType(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'benefit_name' => 'required|string|max:255',
                'benefit_code' => 'required|max:50',
                'calculation_type' => 'required|in:percentage,fixed',
                'calculation_value' => 'required|numeric|min:0',
                'is_taxable' => 'required|boolean',
                'is_active' => 'required|boolean',
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

            $benefitType = BenefitType::create([
                'benefit_name' => $request->benefit_name,
                'benefit_code' => $request->benefit_code,
                'calculation_type' => $request->calculation_type,
                'calculation_value' => $request->calculation_value,
                'is_taxable' => $request->boolean('is_taxable'),
                'is_active' => $request->boolean('is_active'),
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
                'message' => 'Benefit type created successfully',
                'data' => $benefitType,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create benefit type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showBenefitType($id): JsonResponse
    {
        try {
            $benefitType = BenefitType::find($id);

            if (!$benefitType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Benefit type not found',
                ], 404);
            }

            $benefitTypeData = DB::table('bcmis2.benefit_type as bt')
                ->leftJoin('bcmis.auth_user as creator', 'creator.pf_number', '=', 'bt.created_by')
                ->leftJoin('bcmis.auth_user as modifier', 'modifier.pf_number', '=', 'bt.modified_by')
                ->where('bt.benefit_type_id', $id)
                ->select(
                    'bt.*',
                    DB::raw("CONCAT(creator.first_name, ' ', creator.surname) AS created_by"),
                    DB::raw("CONCAT(modifier.first_name, ' ', modifier.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Benefit type retrieved successfully',
                'data' => $benefitTypeData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve benefit type: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateBenefitType(Request $request, $id): JsonResponse
    {
        try {
            $benefitType = BenefitType::find($id);

            if (!$benefitType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Benefit type not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'benefit_name' => 'sometimes|string|max:255',
                'benefit_code' => 'sometimes|string|max:50',
                'calculation_type' => 'sometimes|in:percentage,fixed',
                'calculation_value' => 'sometimes|numeric|min:0',
                'is_taxable' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
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
                'benefit_name',
                'benefit_code',
                'calculation_type',
                'calculation_value',
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

            if ($request->has('is_taxable')) {
                $updateData['is_taxable'] = $request->boolean('is_taxable');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $ratesChanged = false;
            foreach (['calculation_type', 'calculation_value'] as $field) {
                if ($request->has($field) && (string) $request->input($field) !== (string) $benefitType->{$field}) {
                    $ratesChanged = true;
                    break;
                }
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $benefitType->update($updateData);

            if ($ratesChanged) {
                app(MandatoryDeductionProvisioner::class)
                    ->recalculateForBenefitType((int) $benefitType->benefit_type_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Benefit type updated successfully',
                'data' => $benefitType,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update benefit type: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Flip is_active, or set it explicitly with JSON body: { "is_active": true|false }.
     */
    public function toggleBenefitTypeStatus($id): JsonResponse
    {
        try {
            $benefitType = BenefitType::findOrFail($id);
    
            $benefitType->is_active = !$benefitType->is_active;
            $benefitType->modified_by = auth()->user()->pf_number;
            $benefitType->save();
    
            return response()->json([
                'success' => true,
                'message' => $benefitType->is_active
                    ? 'Benefit type activated'
                    : 'Benefit type deactivated',
                'data' => [
                    'benefit_type_id' => $benefitType->benefit_type_id,
                    'is_active' => $benefitType->is_active,
                ],
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyBenefitType($id): JsonResponse
    {
        try {
            $benefitType = BenefitType::find($id);

            if (!$benefitType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Benefit type not found',
                ], 404);
            }

            $benefitType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Benefit type deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete benefit type: ' . $e->getMessage(),
            ], 500);
        }
    }
}

