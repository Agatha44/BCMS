<?php

namespace App\Services\Payroll;

use App\Constants\EmployeeStatus;
use App\Models\Bms\Payroll\EmployeePayrollItem;
use App\Models\Bms\Payroll\PayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollTransactionRowBuilder
{
    private ?array $psssfDeductionTypeIds = null;

    /** @var array<int, int>|null */
    private ?array $beforeTaxDeductionTypeIds = null;

    public function __construct(
        private TaxCalculator $taxCalculator
    ) {}

    /**
     * Build per-employee payroll transaction rows for a run.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildRows(PayrollRun $run): array
    {
        $employees = $this->loadActiveEmployees();

        if ($employees->isEmpty()) {
            return [];
        }

        $itemsByEmployee = $this->loadPayrollItems((int) $run->id);
        $psssfTypeIds = $this->loadPsssfDeductionTypeIds();
        $beforeTaxTypeIds = $this->loadBeforeTaxDeductionTypeIds();

        $periodAsOf = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)
            ->endOfMonth()
            ->startOfDay();

        $rows = [];

        foreach ($employees as $nationalId => $employee) {
            $employeeItems = $itemsByEmployee->get($nationalId, collect());
            $totals = $this->calculateTotals($employeeItems, $psssfTypeIds, $beforeTaxTypeIds);

            $basicSalary = (float) ($employee->basicsalary);

            $grossPay = $basicSalary
                + $totals['benefits']
                + $totals['arrears'];

            $taxablePay = max(0, $grossPay - $totals['before_tax_deductions']);

            $tax = $this->taxCalculator->calculatePaye($taxablePay, $periodAsOf);
            $paye = (float) ($tax['tax']);

            $netPay = $grossPay
                - ($totals['deductions'] + $totals['loans'] + $paye);

            $rows[] = [
                'payroll_run_id' => (int) $run->id,
                'national_id' => (string) $nationalId,

                'payroll_month' => (int) $run->payroll_month,
                'payroll_year' => (int) $run->payroll_year,
                'payroll_number' => $run->payroll_number,

                'pf_number' => $employee->pf_number ?? $employee->pfno,

                'basic_salary' => round($basicSalary, 2),

                'total_arrears' => round($totals['arrears'], 2),
                'total_benefits' => round($totals['benefits'], 2),
                'total_deductions' => round($totals['deductions'], 2),
                'psssf_contribution' => round($totals['psssf'], 2),
                'psssf_employer_contribution' => round($totals['psssf_employer'], 2),
                'total_loans' => round($totals['loans'], 2),

                'gross_pay' => round($grossPay, 2),
                'taxable_pay' => round($taxablePay, 2),
                'paye' => round($paye, 2),
                'net_pay' => round($netPay, 2),

                'bank_id' => $employee->bank_id,
                'account_number' => $employee->account_no,
                'department_id' => $employee->department_id,
                'scheme_id' => $employee->scheme_id,

                'erms_status' => 0,
                'erms_submitted_at' => null,
                'erms_reference' => null,
            ];
        }

        return $rows;
    }

    private function loadActiveEmployees(): Collection
    {
        return DB::connection('bcmis2')
            ->table('bridge_employee as e')
            ->whereIn('e.employee_status', EmployeeStatus::activeValues())
            ->where('e.emptype_id', 3)
            ->select([
                'e.national_id',
                'e.pfno',
                'e.basicsalary',
                'e.bank_id',
                'e.account_no',
                'e.department_id',
                'e.scheme_id',
            ])
            ->get()
            ->keyBy('national_id');
    }

    private function loadPayrollItems(int $runId): Collection
    {
        return EmployeePayrollItem::query()
            ->where('payroll_run_id', $runId)
            ->where('is_void', false)
            ->get()
            ->groupBy('national_id');
    }

    private function calculateTotals(Collection $items, array $psssfDeductionTypeIds, array $beforeTaxDeductionTypeIds): array
    {
        $deductions = $items->where('item_type', 'deduction');

        $psssf = 0.0;
        $psssfEmployer = 0.0;
        if (!empty($psssfDeductionTypeIds)) {
            $psssfItems = $deductions
                ->where('type_table', 'deduction_type')
                ->whereIn('type_id', $psssfDeductionTypeIds);

            $psssf = (float) $psssfItems->sum('amount');
            $psssfEmployer = (float) $psssfItems->sum('employer_amount');
        }

        $beforeTaxDeductions = 0.0;
        if (!empty($beforeTaxDeductionTypeIds)) {
            $beforeTaxDeductions = (float) $deductions
                ->where('type_table', 'deduction_type')
                ->whereIn('type_id', $beforeTaxDeductionTypeIds)
                ->sum('amount');
        }

        return [
            'benefits' => (float) $items->where('item_type', 'benefit')->sum('amount'),
            'deductions' => (float) $deductions->sum('amount'),
            'loans' => (float) $items->where('item_type', 'loan')->sum('amount'),
            'arrears' => (float) $items->where('item_type', 'arrears')->sum('amount'),
            'psssf' => $psssf,
            'psssf_employer' => $psssfEmployer,
            'before_tax_deductions' => $beforeTaxDeductions,
        ];
    }

    /** @return array<int, int> */
    private function loadPsssfDeductionTypeIds(): array
    {
        if ($this->psssfDeductionTypeIds !== null) {
            return $this->psssfDeductionTypeIds;
        }

        $ids = DB::connection('bcmis2')
            ->table('deduction_type')
            ->whereRaw('LOWER(deduction_code) = ?', ['psssf'])
            ->pluck('deduction_type_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $this->psssfDeductionTypeIds = $ids;
        return $ids;
    }

    /** @return array<int, int> */
    private function loadBeforeTaxDeductionTypeIds(): array
    {
        if ($this->beforeTaxDeductionTypeIds !== null) {
            return $this->beforeTaxDeductionTypeIds;
        }

        $ids = DB::connection('bcmis2')
            ->table('deduction_type')
            ->where('is_before_tax', true)
            ->pluck('deduction_type_id')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $this->beforeTaxDeductionTypeIds = $ids;

        return $ids;
    }
}

