<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransaction;
use Illuminate\Support\Facades\DB;

class PayrollProcessor
{
    public function __construct(
        private PayrollTransactionRowBuilder $rowBuilder,
        private LoanRepaymentPostProcessor $loanRepaymentPostProcessor,
    ) {}

    /**
     * Process approved payroll run → generate payroll transactions.
     */
    public function process(PayrollRun $run, string $processedBy = ''): int
    {
        if ($run->status !== 'approved') {
            throw new \Exception('Payroll must be approved before processing.');
        }

        $rows = $this->rowBuilder->buildRows($run);

        DB::connection('bcmis2')->transaction(function () use ($run, $rows, $processedBy) {
            $this->persist($rows);
            $this->loanRepaymentPostProcessor->applyForRun($run, $processedBy);
            $run->update(['status' => 'posted']);
        });

        return count($rows);
    }

    private function persist(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::connection('bcmis2')
                ->table((new PayrollTransaction())->getTable())
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
                    ]
                );
        }
    }
}

