<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Constants\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\EmployeeArrears;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeArrearsController extends Controller
{
    public function ListEmployeeArrears(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search'); // employee national id or payroll number
            $status = $request->get('status'); // is_active
            $workflowStatus = $request->get('workflow_status');
            $paymentStatus = $request->get('payment_status');
            $arrearsReasonId = $request->get('arrears_reason_id');
            $payrollMonth = $request->get('payroll_month');
            $payrollYear = $request->get('payroll_year');

            $sortBy = $request->get('sort_by', 'employee_arrears_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_arrears as ea')
                ->leftJoin('bcmis2.arrears_reasons as ar', 'ar.arrears_reason_id', '=', 'ea.arrears_reason_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'ea.employee_national_id' )
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'ea.created_by')
                ->leftJoin('bcmis.auth_user as vr', 'vr.pf_number', '=', 'ea.verified_by')
                ->leftJoin('bcmis.auth_user as ap', 'ap.pf_number', '=', 'ea.approved_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'ea.modified_by')
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'ea.*',
                    'ar.reason_name as arrears_reason_name',
                    'ar.reason_code as arrears_reason_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(vr.first_name, ' ', vr.surname) AS verified_by"),
                    DB::raw("CONCAT(ap.first_name, ' ', ap.surname) AS approved_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ea.employee_national_id', 'like', "%{$search}%")
                        ->orWhere('ea.payroll_number', 'like', "%{$search}%");
                });
            }

            if ($status !== null) {
                $query->where('ea.is_active', $status);
            }

            if ($workflowStatus !== null) {
                $query->where('ea.workflow_status', $workflowStatus);
            }

            if ($paymentStatus !== null) {
                $query->where('ea.payment_status', $paymentStatus);
            }

            if ($arrearsReasonId !== null) {
                $query->where('ea.arrears_reason_id', $arrearsReasonId);
            }

            if ($payrollMonth !== null) {
                $query->where('ea.payroll_month', $payrollMonth);
            }

            if ($payrollYear !== null) {
                $query->where('ea.payroll_year', $payrollYear);
            }

            $query->orderBy('ea.' . $sortBy, $sortOrder);

            $arrears = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employee arrears retrieved successfully',
                'data' => [
                    $arrears->items(),
                    'pagination' => [
                        'current_page' => $arrears->currentPage(),
                        'last_page' => $arrears->lastPage(),
                        'per_page' => $arrears->perPage(),
                        'total' => $arrears->total(),
                        'from' => $arrears->firstItem(),
                        'to' => $arrears->lastItem(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee arrears: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createEmployeeArrears(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'required|string|max:50',
                'arrears_reason_id' => 'required|integer',
                'payroll_month' => 'nullable|integer|min:1|max:12',
                'payroll_year' => 'nullable|integer|min:2000|max:2500',
                'payroll_number' => 'nullable|string|max:20',
                'arrears_amount' => 'required|numeric|min:0',
                'gross_amount' => 'nullable|numeric|min:0',
                'net_amount' => 'nullable|numeric|min:0',
                'taxable_amount' => 'nullable|numeric|min:0',
                'tax_free_amount' => 'nullable|numeric|min:0',
                'overtime_days' => 'nullable|numeric|min:0',
                'overtime_rate' => 'nullable|numeric|min:0',
                'overtime_gross_amount' => 'nullable|numeric|min:0',
                'overtime_net_amount' => 'nullable|numeric|min:0',
                'arrears_date' => 'nullable|date',
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

            $arrears = EmployeeArrears::create([
                'employee_national_id' => $request->employee_national_id,
                'arrears_reason_id' => $request->arrears_reason_id,
                'payroll_month' => $request->input('payroll_month'),
                'payroll_year' => $request->input('payroll_year'),
                'payroll_number' => $request->input('payroll_number'),
                'arrears_amount' => $request->arrears_amount,
                'gross_amount' => $request->input('gross_amount'),
                'net_amount' => $request->input('net_amount'),
                'taxable_amount' => $request->input('taxable_amount'),
                'tax_free_amount' => $request->input('tax_free_amount'),
                'overtime_days' => $request->input('overtime_days'),
                'overtime_rate' => $request->input('overtime_rate'),
                'overtime_gross_amount' => $request->input('overtime_gross_amount'),
                'overtime_net_amount' => $request->input('overtime_net_amount'),
                'workflow_status' => 'pending',
                'payment_status' => 'unpaid',
                'arrears_date' => $request->input('arrears_date'),
                'is_active' => $request->boolean('is_active', true),
                'notes' => $request->input('notes'),
                'created_by' => auth()->user()->pf_number,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee arrears created successfully',
                'data' => $arrears,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee arrears: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showEmployeeArrears($id): JsonResponse
    {
        try {
            $arrears = EmployeeArrears::find($id);

            if (!$arrears) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee arrears not found',
                ], 404);
            }

            $arrearsData = DB::table('bcmis2.employee_arrears as ea')
                ->leftJoin('bcmis2.arrears_reasons as ar', 'ar.arrears_reason_id', '=', 'ea.arrears_reason_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'ea.created_by')
                ->leftJoin('bcmis.auth_user as vr', 'vr.pf_number', '=', 'ea.verified_by')
                ->leftJoin('bcmis.auth_user as ap', 'ap.pf_number', '=', 'ea.approved_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'ea.modified_by')
                ->where('ea.employee_arrears_id', $id)
                ->select(
                    'ea.*',
                    'ar.reason_name as arrears_reason_name',
                    'ar.reason_code as arrears_reason_code',
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(vr.first_name, ' ', vr.surname) AS verified_by"),
                    DB::raw("CONCAT(ap.first_name, ' ', ap.surname) AS approved_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Employee arrears retrieved successfully',
                'data' => $arrearsData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee arrears: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEmployeeArrears(Request $request, $id): JsonResponse
    {
        try {
            $arrears = EmployeeArrears::find($id);

            if (!$arrears) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee arrears not found',
                ], 404);
            }

            if (in_array($arrears->workflow_status, ['approved', 'paid'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update an approved/paid arrears record',
                ], 409);
            }

            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'sometimes|string|max:50',
                'arrears_reason_id' => 'sometimes|integer',
                'payroll_month' => 'nullable|integer|min:1|max:12',
                'payroll_year' => 'nullable|integer|min:2000|max:2500',
                'payroll_number' => 'nullable|string|max:20',
                'arrears_amount' => 'sometimes|numeric|min:0',
                'gross_amount' => 'nullable|numeric|min:0',
                'net_amount' => 'nullable|numeric|min:0',
                'taxable_amount' => 'nullable|numeric|min:0',
                'tax_free_amount' => 'nullable|numeric|min:0',
                'overtime_days' => 'nullable|numeric|min:0',
                'overtime_rate' => 'nullable|numeric|min:0',
                'overtime_gross_amount' => 'nullable|numeric|min:0',
                'overtime_net_amount' => 'nullable|numeric|min:0',
                'arrears_date' => 'nullable|date',
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
                'arrears_reason_id',
                'payroll_month',
                'payroll_year',
                'payroll_number',
                'arrears_amount',
                'gross_amount',
                'net_amount',
                'taxable_amount',
                'tax_free_amount',
                'overtime_days',
                'overtime_rate',
                'overtime_gross_amount',
                'overtime_net_amount',
                'arrears_date',
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

            $arrears->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Employee arrears updated successfully',
                'data' => $arrears,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee arrears: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleEmployeeArrearsStatus($id): JsonResponse
    {
        try {
            $arrears = EmployeeArrears::findOrFail($id);

            $arrears->is_active = !$arrears->is_active;
            $arrears->modified_by = auth()->user()->pf_number;
            $arrears->save();

            return response()->json([
                'success' => true,
                'message' => $arrears->is_active ? 'Employee arrears activated' : 'Employee arrears deactivated',
                'data' => [
                    'employee_arrears_id' => $arrears->employee_arrears_id,
                    'is_active' => $arrears->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
            ], 500);
        }
    }

    public function destroyEmployeeArrears($id): JsonResponse
    {
        try {
            $arrears = EmployeeArrears::find($id);

            if (!$arrears) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee arrears not found',
                ], 404);
            }

            if (in_array($arrears->workflow_status, ['approved', 'paid'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete an approved/paid arrears record',
                ], 409);
            }

            $arrears->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee arrears deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee arrears: ' . $e->getMessage(),
            ], 500);
        }
    }

/**
 * pending -> verified -> approved
 */

    public function updateWorkflow(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:verify,approve,reject',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
    
            $arrears = EmployeeArrears::findOrFail($id);
            $action = $request->input('action');
    
            // lock only when fully approved and paid
            if ($arrears->workflow_status === 'approved' && $arrears->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify an approved/paid record',
                ], 409);
            }
    
            switch ($action) {
    
                case 'verify':
                    if ($arrears->workflow_status !== 'pending') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only pending records can be verified',
                        ], 409);
                    }
    
                    $arrears->workflow_status = 'verified';
                    $arrears->verified_by = auth()->user()->pf_number;
                    $arrears->verified_at = now();
                    break;
    
                case 'approve':
                    if ($arrears->workflow_status !== 'verified') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Only verified records can be approved',
                        ], 409);
                    }
    
                    $arrears->workflow_status = 'approved';
                    $arrears->approved_by = auth()->user()->pf_number;
                    $arrears->approved_at = now();
                    break;
    
                case 'reject':
                    if (in_array($arrears->workflow_status, ['approved', 'paid', 'rejected'], true)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot reject this record',
                        ], 409);
                    }
    
                    $arrears->workflow_status = 'rejected';
                    $arrears->rejected_by = auth()->user()->pf_number;
                    $arrears->rejected_at = now();
                    break;
            }
    
            $arrears->modified_by = auth()->user()->pf_number;
            $arrears->modified_at = now();
            $arrears->save();
    
            return response()->json([
                'success' => true,
                'message' => 'Workflow updated successfully',
                'data' => $arrears->fresh(),
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workflow: ' . $e->getMessage(),
            ], 500);
        }
    }

}

