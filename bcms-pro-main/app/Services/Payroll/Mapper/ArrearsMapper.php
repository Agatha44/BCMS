<?php

namespace App\Services\Payroll\Mapper;

class ArrearsMapper
{
    /**
     * Canonical payroll item payload for arrears.
     *
     * @return array<string, mixed>
     */
    public function map(object $arrears, int $payrollMonth, int $payrollYear, array $context = []): array
    {
        return [
            'national_id' => (string) (
                $context['national_id']
                ?? $arrears->employee_national_id
                ?? $arrears->national_id
                ?? ''
            ),

            'payroll_month' => (int) (
                $arrears->payroll_month ?? $payrollMonth
            ),

            'payroll_year' => (int) (
                $arrears->payroll_year ?? $payrollYear
            ),

            'item_type' => 'arrears',

            'source_table' => 'employee_arrears',
            'source_id' => (int) ($arrears->employee_arrears_id),

            'type_table' => 'arrears_reason',
            'type_id' => (int) $arrears->arrears_reason_id,

            'name' => (string) $arrears->reason_name,

            'amount' => (float) (
                $arrears->arrears_amount ?? 0
            ),

            'taxed_amount' => (float) ($arrears->taxable_amount ?? 0),
            'taxfree_amount' => (float) ($arrears->tax_free_amount ?? 0),

            'employee_amount' => null,
            'employer_amount' => null,
            'employee_percent' => null,
            'employer_percent' => null,

            'meta' => [
                'payroll_number' => $arrears->payroll_number,

                'gross_amount' => (float) $arrears->gross_amount ?? 0,
                'net_amount' => (float) $arrears->net_amount ?? 0,

                'overtime_days' => $arrears->overtime_days ?? 0,
                'overtime_rate' => $arrears->overtime_rate ?? 0,
                'overtime_gross_amount' => $arrears->overtime_gross_amount ?? 0,
                'overtime_net_amount' => $arrears->overtime_net_amount ?? 0,

                'workflow_status' => $arrears->workflow_status,
                'payment_status' => $arrears->payment_status,

                'arrears_date' => $arrears->arrears_date,
                'is_active' => (bool) $arrears->is_active,

                'notes' => $arrears->notes,

                'source_meta' => $arrears->meta,
            ],

            'created_at' => now(),
        ];
    }
}

