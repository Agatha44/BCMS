<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransactionPreview;
use Illuminate\Support\Facades\DB;

class PayrollPreviewProcessor
{
    public function __construct(
        private PayrollTransactionRowBuilder $rowBuilder
    ) {}

    /**
     * Generate preview payroll transactions for a run (NO posting).
     *
     * @return int Number of employee rows written
     */
    public function generate(PayrollRun $run, string $generatedBy): int
    {
        $now = now();

        $rows = array_map(function (array $row) use ($now, $generatedBy) {
            $row['generated_at'] = $now;
            $row['generated_by'] = $generatedBy;
            return $row;
        }, $this->rowBuilder->buildRows($run));

        $this->persist($rows);

        return count($rows);
    }

    private function persist(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        DB::connection('bcmis2')->transaction(function () use ($rows) {
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::connection('bcmis2')
                    ->table((new PayrollTransactionPreview())->getTable())
                    ->upsert(
                        $chunk,
                        ['payroll_run_id', 'national_id'],
                        [
                            'payroll_month',
                            'payroll_year',
                            'payroll_number',
                            'pf_number',
                            'basic_salary',
                            'total_arrears',
                            'total_benefits',
                            'total_deductions',
                            'psssf_contribution',
                            'psssf_employer_contribution',
                            'total_loans',
                            'gross_pay',
                            'taxable_pay',
                            'paye',
                            'net_pay',
                            'bank_id',
                            'account_number',
                            'department_id',
                            'scheme_id',
                            'erms_status',
                            'erms_submitted_at',
                            'erms_reference',
                            'generated_at',
                            'generated_by',
                        ]
                    );
            }
        });
    }
}

