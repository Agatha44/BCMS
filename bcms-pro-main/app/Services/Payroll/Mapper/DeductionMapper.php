<?php

namespace App\Services\Payroll\Mapper;

class DeductionMapper
{
    /**
     * Canonical payroll item payload.
     *
     * @return array<string, mixed>
     */
    public function map(object $deduction, int $payrollMonth, int $payrollYear, array $context = []): array
    {
        return [
            'national_id' => (string) $deduction->employee_national_id,

            'payroll_month' => $payrollMonth,
            'payroll_year' => $payrollYear,

            'item_type' => 'deduction',

            'source_table' => 'employee_deduction',
            'source_id' => (int) $deduction->employee_deduction_id,

            'type_table' => 'deduction_type',
            'type_id' => (int) $deduction->deduction_type_id,

            'name' => (string) $deduction->deduction_name,

            'amount' => (float) ($deduction->employee_contribution_amount ?? 0),
            'taxed_amount' => (float) ($deduction->taxable_amount ?? 0),
            'taxfree_amount' => (float) ($deduction->tax_free_amount ?? 0),

            'employee_amount' => (float) ($deduction->employee_contribution_amount ?? 0),
            'employer_amount' => (float) ($deduction->employer_contribution_amount ?? 0),
            'employee_percent' => (float) ($deduction->employee_contribution_percentage ?? 0),
            'employer_percent' => (float) ($deduction->employer_contribution_percentage ?? 0),

            'meta' => [
                'is_before_tax' => (bool) ($deduction->is_before_tax ?? false),
                'effective_start_date' => $deduction->effective_start_date,
                'effective_end_date' => $deduction->effective_end_date,
                'is_active' => (bool) $deduction->is_active,
                'notes' => $deduction->notes,
                'source_meta' => $deduction->meta,
            ],

            'created_at' => now(),
        ];
    }
}