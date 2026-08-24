<?php

namespace App\Services\Payroll\Mapper;

class LoanMapper
{
    /**
     * Canonical payroll item payload.
     *
     * @return array<string, mixed>
     */
    public function map(object $loan, int $payrollMonth, int $payrollYear, array $context = []): array
    {
        return [
            'national_id' => (string) $loan->employee_national_id,

            'payroll_month' => $payrollMonth,
            'payroll_year' => $payrollYear,

            'item_type' => 'loan',

            'source_table' => 'employee_loan',
            'source_id' => (int) $loan->employee_loan_id,

            'type_table' => 'loan_type',
            'type_id' => (int) $loan->loan_type_id,

            'name' => (string) ($loan->loan_name ?? 'Loan'),

            // Monthly repayment is what affects payroll deduction
            'amount' => (float) (
                $loan->monthly_total_repayment_amount
                ?? 0
            ),

            // Loan is a deduction, so employee_amount is same as amount
            'employee_amount' => (float) (
                $loan->monthly_total_repayment_amount
                ?? 0
            ),

            'employer_amount' => null,
            'employee_percent' => null,
            'employer_percent' => null,

            'taxed_amount' => null,
            'taxfree_amount' => null,

            'meta' => [
                'loan_reference_number' => $loan->loan_reference_number,
                'loan_issue_date' => $loan->loan_issue_date,
                'repayment_start_date' => $loan->repayment_start_date,

                'principal_amount' => (float) $loan->principal_amount,
                'total_interest_amount' => (float) $loan->total_interest_amount,
                'repayment_period_months' => (int) $loan->repayment_period_months,

                'monthly_principal_amount' => $loan->monthly_principal_amount,
                'monthly_interest_amount' => $loan->monthly_interest_amount,

                'total_repaid_amount' => $loan->total_repaid_amount,
                'outstanding_balance_amount' => $loan->outstanding_balance_amount,

                'loan_status' => $loan->loan_status,

                'effective_start_date' => $loan->effective_start_date,
                'effective_end_date' => $loan->effective_end_date,
                'is_active' => (bool) $loan->is_active,

                'notes' => $loan->notes,
                'source_meta' => $context['meta'] ?? null,
            ],

            'created_at' => now(),
        ];
    }
}