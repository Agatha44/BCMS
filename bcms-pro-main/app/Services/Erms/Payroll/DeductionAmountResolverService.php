<?php

namespace App\Services\Erms\Payroll;

use App\Models\Bms\Payroll\PayrollRun;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeductionAmountResolverService
{
    public function __construct(
        private PayrollDeductionPayableConfig $payableConfig,
        private ?DeductionAccountResolver $deductionAccountResolver = null,
    ) {}

    /**
     * @return array{total: float, employee?: float, employer?: float}
     */
    public function resolve(PayrollRun $run, string $kind): array
    {
        $definition = $this->payableConfig->definition($kind);
        $amountSource = (string) ($definition['amount_source'] ?? 'deduction_items');

        return match ($amountSource) {
            'transaction_column' => $this->fromTransactionColumn($run, (string) ($definition['transaction_column'] ?? '')),
            'psssf_split' => $this->splitFromPsssfTransactionColumns($run),
            default => $this->payableConfig->kindHasEmployerContribution($kind)
                ? $this->splitFromDeductionItems($run, $kind)
                : $this->fromDeductionItems($run, $kind),
        };
    }

    /**
     * @return list<string>
     */
    public function kindsWithAmounts(PayrollRun $run): array
    {
        $kinds = [];
        foreach ($this->payableConfig->kinds() as $kind) {
            if (! $this->payableConfig->isEnabled($kind)) {
                continue;
            }
            if ($this->resolve($run, $kind)['total'] > 0) {
                $kinds[] = $kind;
            }
        }

        return $kinds;
    }

    /**
     * @return array{total: float}
     */
    private function fromTransactionColumn(PayrollRun $run, string $column): array
    {
        $column = trim($column);
        if ($column === '') {
            return ['total' => 0.0];
        }

        $value = DB::connection('bcmis2')
            ->table('payroll_transaction')
            ->where('payroll_run_id', $run->id)
            ->sum($column);

        return ['total' => round((float) $value, 2)];
    }

    /**
     * PSSSF employee + employer totals from processed payroll transactions.
     *
     * @return array{total: float, employee: float, employer: float}
     */
    private function splitFromPsssfTransactionColumns(PayrollRun $run): array
    {
        $row = DB::connection('bcmis2')
            ->table('payroll_transaction')
            ->where('payroll_run_id', $run->id)
            ->selectRaw('
                COALESCE(SUM(psssf_contribution), 0) as employee_total,
                COALESCE(SUM(psssf_employer_contribution), 0) as employer_total
            ')
            ->first();

        $employee = round((float) ($row->employee_total ?? 0), 2);
        $employer = round((float) ($row->employer_total ?? 0), 2);

        return [
            'employee' => $employee,
            'employer' => $employer,
            'total' => round($employee + $employer, 2),
        ];
    }

    /**
     * @return array{total: float, employee: float, employer: float}
     */
    private function splitFromDeductionItems(PayrollRun $run, string $kind): array
    {
        $kind = $this->payableConfig->normalizeKind($kind);
        $typeIds = $this->payableConfig->deductionTypeIdsForKind($kind);

        $employee = 0.0;
        $employer = 0.0;

        if ($typeIds !== []) {
            $row = DB::connection('bcmis2')
                ->table('employee_payroll_items as i')
                ->join('payroll_transaction as t', function ($join) {
                    $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                        ->on('t.national_id', '=', 'i.national_id');
                })
                ->where('i.payroll_run_id', $run->id)
                ->where('i.is_void', false)
                ->where('i.item_type', 'deduction')
                ->where('i.type_table', 'deduction_type')
                ->whereIn('i.type_id', $typeIds)
                ->selectRaw('
                    COALESCE(SUM(
                        CASE
                            WHEN COALESCE(i.employee_amount, 0) <> 0 THEN i.employee_amount
                            ELSE i.amount
                        END
                    ), 0) as employee_total,
                    COALESCE(SUM(COALESCE(i.employer_amount, 0)), 0) as employer_total
                ')
                ->first();

            $employee = round((float) ($row->employee_total ?? 0), 2);
            $employer = round((float) ($row->employer_total ?? 0), 2);
        }

        return [
            'employee' => $employee,
            'employer' => $employer,
            'total' => round($employee + $employer, 2),
        ];
    }

    /**
     * @return array{total: float}
     */
    private function fromDeductionItems(PayrollRun $run, string $kind): array
    {
        $kind = $this->payableConfig->normalizeKind($kind);
        $total = 0.0;

        foreach ($this->loadDeductionRows($run) as $row) {
            if ($this->deductionAccountResolver()->tryClassify($row) !== $kind) {
                continue;
            }
            $total += (float) ($row->amount ?? 0);
        }

        return ['total' => round($total, 2)];
    }

    /**
     * @return Collection<int, object>
     */
    private function loadDeductionRows(PayrollRun $run): Collection
    {
        return DB::connection('bcmis2')
            ->table('employee_payroll_items as i')
            ->join('payroll_transaction as t', function ($join) {
                $join->on('t.payroll_run_id', '=', 'i.payroll_run_id')
                    ->on('t.national_id', '=', 'i.national_id');
            })
            ->leftJoin('deduction_type as dt', function ($join) {
                $join->on('dt.deduction_type_id', '=', 'i.type_id')
                    ->where('i.type_table', '=', 'deduction_type');
            })
            ->where('i.payroll_run_id', $run->id)
            ->where('i.is_void', false)
            ->where('i.item_type', 'deduction')
            ->groupBy('i.type_id', 'dt.deduction_code', 'display_name')
            ->select([
                'i.type_id',
                DB::raw("COALESCE(NULLIF(dt.deduction_code, ''), '') as deduction_code"),
                DB::raw("COALESCE(NULLIF(i.name, ''), dt.deduction_name, 'Deduction') as display_name"),
                DB::raw('COALESCE(SUM(i.amount), 0) as amount'),
            ])
            ->get();
    }

    private function deductionAccountResolver(): DeductionAccountResolver
    {
        return $this->deductionAccountResolver ??= new DeductionAccountResolver();
    }
}
