<?php

namespace App\Services\Payroll\Mapper;

class BenefitMapper
{
    /**
     * Canonical payroll item payload.
     *
     * @return array<string, mixed>
     */
    public function map(object $benefit, int $payrollMonth, int $payrollYear, array $context = []): array
    {
        return [
            'national_id' => (string) $benefit->employee_national_id,

            'payroll_month' => $payrollMonth,
            'payroll_year' => $payrollYear,

            'item_type' => 'benefit',

            'source_table' => 'employee_benefit',
            'source_id' => (int) $benefit->employee_benefit_id,

            'type_table' => 'benefit_type',
            'type_id' => (int) $benefit->benefit_type_id,

            'name' => (string) $benefit->benefit_name,

            'amount' => (float) $benefit->benefit_amount,
            'taxed_amount' => (float) ($benefit->taxable_amount ?? 0),
            'taxfree_amount' => (float) ($benefit->tax_free_amount ?? 0),

            'employee_amount' => null,
            'employer_amount' => null,
            'employee_percent' => null,
            'employer_percent' => null,

            'meta' => [
                'effective_start_date' => $benefit->effective_start_date,
                'effective_end_date' => $benefit->effective_end_date,
                'is_active' => (bool) $benefit->is_active,
                'notes' => $benefit->notes,
                'source_meta' => $context['meta'] ?? null,
            ],

            'created_at' => now(),
        ];
    }
}