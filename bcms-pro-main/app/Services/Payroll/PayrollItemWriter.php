<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\EmployeePayrollItem;
use App\Models\Bms\Payroll\PayrollRun;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PayrollItemWriter
{
    public function __construct(
        private PayrollItemBuilder $builder,
    ) {}

    /**
     * Build payroll items for a run period and upsert into employee_payroll_items.
     *
     * @return int Number of rows attempted (input row count)
     */
    public function writeForRun(PayrollRun $run): int
    {
        $items = $this->builder->build((int) $run->payroll_month, (int) $run->payroll_year);

        $rows = array_map(function (array $item) use ($run) {
            $row = Arr::only($item, (new EmployeePayrollItem())->getFillable());

            $row['payroll_run_id'] = (int) $run->id;
            $row['is_void'] = (bool) ($row['is_void'] ?? false);

            // Query builder upsert needs explicit JSON for meta.
            if (array_key_exists('meta', $row) && is_array($row['meta'])) {
                $row['meta'] = json_encode($row['meta'], JSON_UNESCAPED_UNICODE);
            }

            return $row;
        }, $items);

        if (empty($rows)) {
            return 0;
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::connection('bcmis2')
                ->table('employee_payroll_items')
                ->upsert(
                    $chunk,
                    ['payroll_run_id', 'national_id', 'item_type', 'source_table', 'source_id'],
                    [
                        'payroll_month',
                        'payroll_year',
                        'type_table',
                        'type_id',
                        'name',
                        'amount',
                        'taxed_amount',
                        'taxfree_amount',
                        'employee_amount',
                        'employer_amount',
                        'employee_percent',
                        'employer_percent',
                        'meta',
                    ]
                );
        }

        return count($rows);
    }
}

