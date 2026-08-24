<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\EmployeeArrears;
use App\Models\Bms\Payroll\EmployeeBenefit;
use App\Models\Bms\Payroll\EmployeeDeduction;
use App\Models\Bms\Payroll\EmployeeLoan;
use App\Services\Payroll\Mapper\ArrearsMapper;
use App\Services\Payroll\Mapper\BenefitMapper;
use App\Services\Payroll\Mapper\DeductionMapper;
use App\Services\Payroll\Mapper\LoanMapper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class PayrollItemBuilder
{
    public function __construct(
        private BenefitMapper $benefitMapper,
        private DeductionMapper $deductionMapper,
        private LoanMapper $loanMapper,
        private ArrearsMapper $arrearsMapper,
    ) {}

    /**
     * Build ALL payroll items for a payroll run period (NO DB WRITE).
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(int $payrollMonth, int $payrollYear): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($payrollMonth, $payrollYear);

        $items = [];

        $benefits = EmployeeBenefit::query()
            ->active()
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->get();

        foreach ($benefits as $benefit) {
            $items[] = $this->benefitMapper->map($benefit, $payrollMonth, $payrollYear);
        }

        $deductions = EmployeeDeduction::query()
            ->active()
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->get();

        foreach ($deductions as $deduction) {
            $item = $this->deductionMapper->map($deduction, $payrollMonth, $payrollYear);
            if (((float) ($item['amount'] ?? 0)) !== 0.0) {
                $items[] = $item;
            }
        }

        $loans = EmployeeLoan::query()
            ->active()
            ->where('loan_status', 'active')
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('repayment_start_date')
                    ->orWhereDate('repayment_start_date', '<=', $periodEnd);
            })
            ->where(function ($q) {
                $q->whereNull('outstanding_balance_amount')
                    ->orWhere('outstanding_balance_amount', '>', 0);
            })
            ->whereDoesntHave('repayments', function ($q) use ($payrollMonth, $payrollYear) {
                $q->where('repayment_source', 'payroll')
                    ->where('payroll_month', $payrollMonth)
                    ->where('payroll_year', $payrollYear);
            })
            ->get();

        foreach ($loans as $loan) {
            $item = $this->loanMapper->map($loan, $payrollMonth, $payrollYear);
            if (((float) ($item['amount'] ?? 0)) !== 0.0) {
                $items[] = $item;
            }
        }

        $arrears = EmployeeArrears::query()
            ->active()
            ->where('payroll_month', $payrollMonth)
            ->where('payroll_year', $payrollYear)
            ->where('workflow_status', 'approved')
            ->where('payment_status', 'unpaid')
            ->get();

        foreach ($arrears as $arrear) {
            $items[] = $this->arrearsMapper->map($arrear, $payrollMonth, $payrollYear);
        }

        return array_values(array_filter($items, fn ($i) => ! empty(Arr::get($i, 'national_id'))));
    }

    /**
     * Build items for a single employee (useful for preview UI).
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildForEmployee(string $nationalId, int $payrollMonth, int $payrollYear): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($payrollMonth, $payrollYear);

        $items = [];

        $benefits = EmployeeBenefit::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->get();

        foreach ($benefits as $benefit) {
            $items[] = $this->benefitMapper->map($benefit, $payrollMonth, $payrollYear);
        }

        $deductions = EmployeeDeduction::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->get();

        foreach ($deductions as $deduction) {
            $item = $this->deductionMapper->map($deduction, $payrollMonth, $payrollYear);
            if (((float) ($item['amount'] ?? 0)) !== 0.0) {
                $items[] = $item;
            }
        }

        $loans = EmployeeLoan::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->where('loan_status', 'active')
            ->effectiveForPeriod($periodStart, $periodEnd)
            ->where(function ($q) use ($periodEnd) {
                $q->whereNull('repayment_start_date')
                    ->orWhereDate('repayment_start_date', '<=', $periodEnd);
            })
            ->where(function ($q) {
                $q->whereNull('outstanding_balance_amount')
                    ->orWhere('outstanding_balance_amount', '>', 0);
            })
            ->whereDoesntHave('repayments', function ($q) use ($payrollMonth, $payrollYear) {
                $q->where('repayment_source', 'payroll')
                    ->where('payroll_month', $payrollMonth)
                    ->where('payroll_year', $payrollYear);
            })
            ->get();

        foreach ($loans as $loan) {
            $item = $this->loanMapper->map($loan, $payrollMonth, $payrollYear);
            if (((float) ($item['amount'] ?? 0)) !== 0.0) {
                $items[] = $item;
            }
        }

        $arrears = EmployeeArrears::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->where('payroll_month', $payrollMonth)
            ->where('payroll_year', $payrollYear)
            ->where('workflow_status', 'approved')
            ->where('payment_status', 'unpaid')
            ->get();

        foreach ($arrears as $arrear) {
            $items[] = $this->arrearsMapper->map($arrear, $payrollMonth, $payrollYear);
        }

        return array_values(array_filter($items, fn ($i) => ! empty(Arr::get($i, 'national_id'))));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBounds(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $end = $start->endOfMonth()->endOfDay();

        return [$start, $end];
    }
}

