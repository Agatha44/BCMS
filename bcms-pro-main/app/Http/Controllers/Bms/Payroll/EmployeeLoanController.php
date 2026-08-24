<?php

namespace App\Http\Controllers\Bms\Payroll;

use App\Constants\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\Bms\Payroll\EmployeeLoan;
use App\Models\Bms\Payroll\LoanType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeLoanController extends Controller
{
    public function ListEmployeeLoans(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $status = $request->get('status');
            $loanTypeId = $request->get('loan_type_id');
            $loanStatus = $request->get('loan_status');
            $effectiveStartDate = $request->get('effective_start_date');
            $effectiveEndDate = $request->get('effective_end_date');

            $sortBy = $request->get('sort_by', 'employee_loan_id');
            $sortOrder = $request->get('sort_order', 'desc');
            $perPage = $request->get('per_page', 15);

            $query = DB::table('bcmis2.employee_loan as el')
                ->leftJoin('bcmis2.loan_type as lt', 'lt.loan_type_id', '=', 'el.loan_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'el.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'el.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'el.modified_by')
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'el.*',
                    'lt.loan_name as loan_name',
                    'lt.loan_code as loan_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                );

            if (!empty($search)) {
                $term = '%' . trim($search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('el.employee_national_id', 'like', $term)
                        ->orWhere('el.loan_reference_number', 'like', $term)
                        ->orWhere('lt.loan_name', 'like', $term)
                        ->orWhere('lt.loan_code', 'like', $term)
                        ->orWhere(DB::raw("CONCAT(be.fname, ' ', be.sname)"), 'like', $term);
                });
            }

            if ($status !== null) {
                $query->where('el.is_active', $status);
            }

            if ($loanTypeId !== null) {
                $query->where('el.loan_type_id', $loanTypeId);
            }

            if ($loanStatus !== null) {
                $query->where('el.loan_status', $loanStatus);
            }

            if ($effectiveStartDate !== null) {
                $query->whereDate('el.effective_start_date', '>=', $effectiveStartDate);
            }

            if ($effectiveEndDate !== null) {
                $query->whereDate('el.effective_end_date', '<=', $effectiveEndDate);
            }

            $query->orderBy('el.' . $sortBy, $sortOrder);

            $loans = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Employee loans retrieved successfully',
                'data' => $loans->items(),
                    'pagination' => [
                        'current_page' => $loans->currentPage(),
                        'last_page' => $loans->lastPage(),
                        'per_page' => $loans->perPage(),
                        'total' => $loans->total(),
                        'from' => $loans->firstItem(),
                        'to' => $loans->lastItem(),
                    ],
                ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee loans: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createEmployeeLoan(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_national_id' => 'required|string|max:50',
                'loan_type_id' => 'required|integer',
                'principal_amount' => 'required|numeric|min:0.01',
                'repayment_period_months' => 'required|integer|min:1',
                'loan_reference_number' => 'nullable|string|max:100',
                'loan_issue_date' => 'nullable|date',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
                'is_active' => 'required|boolean',
                'notes' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $employee = DB::connection('bcmis2')
                ->table('bridge_employee')
                ->where('national_id', $request->employee_national_id)
                ->whereIn('employee_status', EmployeeStatus::activeValues())
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active employee not found for the provided national ID',
                ], 422);
            }

            $loanType = LoanType::query()
                ->where('loan_type_id', $request->loan_type_id)
                ->where('is_active', true)
                ->first();

            if (!$loanType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active loan type not found',
                ], 422);
            }

            $principalAmount = (float) $request->principal_amount;
            $repaymentPeriodMonths = (int) $request->repayment_period_months;

            if ($loanType->minimum_loan_amount !== null && $principalAmount < (float) $loanType->minimum_loan_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Principal amount is below the minimum allowed for this loan type',
                ], 422);
            }

            if ($loanType->maximum_loan_amount !== null && $principalAmount > (float) $loanType->maximum_loan_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Principal amount exceeds the maximum allowed for this loan type',
                ], 422);
            }

            if ($loanType->minimum_repayment_months !== null && $repaymentPeriodMonths < (int) $loanType->minimum_repayment_months) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment period is below the minimum allowed for this loan type',
                ], 422);
            }

            if ($loanType->maximum_repayment_months !== null && $repaymentPeriodMonths > (int) $loanType->maximum_repayment_months) {
                return response()->json([
                    'success' => false,
                    'message' => 'Repayment period exceeds the maximum allowed for this loan type',
                ], 422);
            }

            $schedule = $this->calculateLoanSchedule($loanType, $principalAmount, $repaymentPeriodMonths);
            $totalRepayable = round($principalAmount + $schedule['total_interest_amount'], 2);

            $loan = EmployeeLoan::create([
                'employee_national_id' => $request->employee_national_id,
                'loan_type_id' => $request->loan_type_id,
                'loan_reference_number' => $request->input('loan_reference_number'),
                'loan_issue_date' => $request->input('loan_issue_date'),
                'repayment_start_date' => $request->input('effective_start_date'),
                'principal_amount' => $principalAmount,
                'total_interest_amount' => $schedule['total_interest_amount'],
                'repayment_period_months' => $repaymentPeriodMonths,
                'monthly_principal_amount' => $schedule['monthly_principal_amount'],
                'monthly_interest_amount' => $schedule['monthly_interest_amount'],
                'monthly_total_repayment_amount' => $schedule['monthly_total_repayment_amount'],
                'total_repaid_amount' => 0,
                'outstanding_balance_amount' => $totalRepayable,
                'loan_status' => 'active',
                'effective_start_date' => $request->input('effective_start_date'),
                'effective_end_date' => $request->input('effective_end_date'),
                'is_active' => $request->boolean('is_active'),
                'notes' => $request->input('notes'),
                'created_by' => auth()->user()->pf_number,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee loan created successfully',
                'data' => $loan,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee loan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function showEmployeeLoan($id): JsonResponse
    {
        try {
            $loan = EmployeeLoan::find($id);

            if (!$loan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee loan not found',
                ], 404);
            }

            $loanData = DB::table('bcmis2.employee_loan as el')
                ->leftJoin('bcmis2.loan_type as lt', 'lt.loan_type_id', '=', 'el.loan_type_id')
                ->leftJoin('bcmis2.bridge_employee as be', 'be.national_id', '=', 'el.employee_national_id')
                ->leftJoin('bcmis.auth_user as cr', 'cr.pf_number', '=', 'el.created_by')
                ->leftJoin('bcmis.auth_user as md', 'md.pf_number', '=', 'el.modified_by')
                ->where('el.employee_loan_id', $id)
                ->whereIn('be.employee_status', EmployeeStatus::activeValues())
                ->select(
                    'el.*',
                    'lt.loan_name as loan_name',
                    'lt.loan_code as loan_code',
                    DB::raw("CONCAT(be.fname, ' ', be.sname) AS employee_name"),
                    DB::raw("CONCAT(cr.first_name, ' ', cr.surname) AS created_by"),
                    DB::raw("CONCAT(md.first_name, ' ', md.surname) AS modified_by")
                )
                ->first();

            if (!$loanData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee loan not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee loan retrieved successfully',
                'data' => $loanData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee loan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateEmployeeLoan(Request $request, $id): JsonResponse
    {
        try {
            $loan = EmployeeLoan::find($id);

            if (!$loan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee loan not found',
                ], 404);
            }

            if (in_array($loan->loan_status, ['completed', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update a completed or cancelled employee loan',
                ], 409);
            }

            if ($request->has('employee_national_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee cannot be changed on an existing loan',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'loan_type_id' => 'sometimes|integer',
                'principal_amount' => 'sometimes|numeric|min:0.01',
                'repayment_period_months' => 'sometimes|integer|min:1',
                'loan_reference_number' => 'nullable|string|max:100',
                'loan_issue_date' => 'nullable|date',
                'effective_start_date' => 'nullable|date',
                'effective_end_date' => 'nullable|date|after_or_equal:effective_start_date',
                'loan_status' => 'sometimes|string|in:active,suspended',
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

            $hasRepayments = (float) ($loan->total_repaid_amount ?? 0) > 0;
            $financialFields = ['loan_type_id', 'principal_amount', 'repayment_period_months'];

            foreach ($financialFields as $field) {
                if ($request->has($field) && $hasRepayments) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot change loan terms after repayments have been recorded',
                    ], 409);
                }
            }

            $updateData = [];
            foreach ([
                'loan_type_id',
                'loan_reference_number',
                'loan_issue_date',
                'effective_start_date',
                'effective_end_date',
                'loan_status',
                'notes',
            ] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            if ($request->has('effective_start_date')) {
                $updateData['repayment_start_date'] = $request->input('effective_start_date');
            }

            $loanTypeId = $request->input('loan_type_id', $loan->loan_type_id);
            $principalAmount = $request->has('principal_amount')
                ? (float) $request->principal_amount
                : (float) $loan->principal_amount;
            $repaymentPeriodMonths = $request->has('repayment_period_months')
                ? (int) $request->repayment_period_months
                : (int) $loan->repayment_period_months;

            $financialTermsChanged = $request->hasAny([
                'loan_type_id',
                'principal_amount',
                'repayment_period_months',
            ]);

            if ($financialTermsChanged) {
                $loanType = LoanType::query()
                    ->where('loan_type_id', $loanTypeId)
                    ->where('is_active', true)
                    ->first();

                if (!$loanType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Active loan type not found',
                    ], 422);
                }

                if ($loanType->minimum_loan_amount !== null && $principalAmount < (float) $loanType->minimum_loan_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Principal amount is below the minimum allowed for this loan type',
                    ], 422);
                }

                if ($loanType->maximum_loan_amount !== null && $principalAmount > (float) $loanType->maximum_loan_amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Principal amount exceeds the maximum allowed for this loan type',
                    ], 422);
                }

                if ($loanType->minimum_repayment_months !== null && $repaymentPeriodMonths < (int) $loanType->minimum_repayment_months) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Repayment period is below the minimum allowed for this loan type',
                    ], 422);
                }

                if ($loanType->maximum_repayment_months !== null && $repaymentPeriodMonths > (int) $loanType->maximum_repayment_months) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Repayment period exceeds the maximum allowed for this loan type',
                    ], 422);
                }

                $schedule = $this->calculateLoanSchedule($loanType, $principalAmount, $repaymentPeriodMonths);
                $totalRepayable = round($principalAmount + $schedule['total_interest_amount'], 2);

                $updateData['loan_type_id'] = $loanTypeId;
                $updateData['principal_amount'] = $principalAmount;
                $updateData['repayment_period_months'] = $repaymentPeriodMonths;
                $updateData['total_interest_amount'] = $schedule['total_interest_amount'];
                $updateData['monthly_principal_amount'] = $schedule['monthly_principal_amount'];
                $updateData['monthly_interest_amount'] = $schedule['monthly_interest_amount'];
                $updateData['monthly_total_repayment_amount'] = $schedule['monthly_total_repayment_amount'];
                $updateData['outstanding_balance_amount'] = $totalRepayable;
            }

            $updateData['modified_by'] = auth()->user()->pf_number;
            $updateData['modified_at'] = now();

            $loan->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Employee loan updated successfully',
                'data' => $loan->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee loan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function toggleEmployeeLoan($id): JsonResponse
    {
        try {
            $loan = EmployeeLoan::findOrFail($id);

            $loan->is_active = !$loan->is_active;
            $loan->modified_by = auth()->user()->pf_number;
            $loan->modified_at = now();
            $loan->save();

            return response()->json([
                'success' => true,
                'message' => $loan->is_active ? 'Employee loan activated' : 'Employee loan deactivated',
                'data' => [
                    'employee_loan_id' => $loan->employee_loan_id,
                    'is_active' => $loan->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle employee loan status',
            ], 500);
        }
    }

    /**
     * @return array{
     *     total_interest_amount: float,
     *     monthly_principal_amount: float,
     *     monthly_interest_amount: float,
     *     monthly_total_repayment_amount: float
     * }
     */
    private function calculateLoanSchedule(LoanType $loanType, float $principal, int $months): array
    {
        if (!$loanType->has_interest || !$loanType->interest_percentage) {
            $monthlyPrincipal = round($principal / $months, 2);

            return [
                'total_interest_amount' => 0,
                'monthly_principal_amount' => $monthlyPrincipal,
                'monthly_interest_amount' => 0,
                'monthly_total_repayment_amount' => $monthlyPrincipal,
            ];
        }

        $rate = (float) $loanType->interest_percentage;
        $method = $loanType->interest_calculation_method ?? 'flat';

        if ($method === 'reducing_balance') {
            $monthlyRate = $rate / 100 / 12;

            if ($monthlyRate <= 0) {
                $monthlyPrincipal = round($principal / $months, 2);

                return [
                    'total_interest_amount' => 0,
                    'monthly_principal_amount' => $monthlyPrincipal,
                    'monthly_interest_amount' => 0,
                    'monthly_total_repayment_amount' => $monthlyPrincipal,
                ];
            }

            $factor = pow(1 + $monthlyRate, $months);
            $emi = round($principal * $monthlyRate * $factor / ($factor - 1), 2);
            $totalInterest = round(($emi * $months) - $principal, 2);
            $firstMonthInterest = round($principal * $monthlyRate, 2);
            $firstMonthPrincipal = round($emi - $firstMonthInterest, 2);

            return [
                'total_interest_amount' => $totalInterest,
                'monthly_principal_amount' => $firstMonthPrincipal,
                'monthly_interest_amount' => $firstMonthInterest,
                'monthly_total_repayment_amount' => $emi,
            ];
        }

        $totalInterest = round($principal * ($rate / 100) * ($months / 12), 2);
        $monthlyPrincipal = round($principal / $months, 2);
        $monthlyInterest = round($totalInterest / $months, 2);

        return [
            'total_interest_amount' => $totalInterest,
            'monthly_principal_amount' => $monthlyPrincipal,
            'monthly_interest_amount' => $monthlyInterest,
            'monthly_total_repayment_amount' => round($monthlyPrincipal + $monthlyInterest, 2),
        ];
    }
}
