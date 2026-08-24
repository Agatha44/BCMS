<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\EmployeeLoan;
use App\Models\Bms\Payroll\EmployeeLoanRepayment;
use App\Models\Bms\Payroll\EmployeePayrollItem;
use App\Models\Bms\Payroll\PayrollRun;
use Carbon\Carbon;

class LoanRepaymentPostProcessor
{
    /**
     * Record payroll loan repayments and update loan balances for a posted run.
     *
     * @return int Number of repayment rows created
     */
    public function applyForRun(PayrollRun $run, string $processedBy = ''): int
    {
        $items = EmployeePayrollItem::query()
            ->where('payroll_run_id', (int) $run->id)
            ->where('item_type', 'loan')
            ->where('source_table', 'employee_loan')
            ->where('is_void', false)
            ->where('amount', '>', 0)
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $created = 0;
        $repaymentDate = Carbon::create((int) $run->payroll_year, (int) $run->payroll_month, 1)
            ->endOfMonth()
            ->startOfDay();

        foreach ($items as $item) {
            if ($this->recordRepaymentForItem($run, $item, $repaymentDate, $processedBy)) {
                $created++;
            }
        }

        return $created;
    }

    private function recordRepaymentForItem(
        PayrollRun $run,
        EmployeePayrollItem $item,
        Carbon $repaymentDate,
        string $processedBy
    ): bool {
        $loanId = (int) $item->source_id;

        $loan = EmployeeLoan::query()
            ->whereKey($loanId)
            ->lockForUpdate()
            ->first();

        if (! $loan) {
            return false;
        }

        $alreadyRecorded = EmployeeLoanRepayment::query()
            ->where('employee_loan_id', $loanId)
            ->where('payroll_month', (int) $run->payroll_month)
            ->where('payroll_year', (int) $run->payroll_year)
            ->where('repayment_source', 'payroll')
            ->exists();

        if ($alreadyRecorded) {
            return false;
        }

        $opening = $this->resolveOpeningBalance($loan);

        if ($opening <= 0) {
            return false;
        }

        $scheduledTotal = (float) $item->amount;
        $repaymentTotal = round(min($scheduledTotal, $opening), 2);

        if ($repaymentTotal <= 0) {
            return false;
        }

        [$principalAmount, $interestAmount] = $this->splitRepaymentAmounts($loan, $repaymentTotal);
        $closing = round(max(0, $opening - $repaymentTotal), 2);

        EmployeeLoanRepayment::query()->create([
            'employee_loan_id' => $loanId,
            'repayment_date' => $repaymentDate,
            'payroll_month' => (int) $run->payroll_month,
            'payroll_year' => (int) $run->payroll_year,
            'payroll_number' => $this->resolvePayrollNumber($run->payroll_number),
            'repayment_total_amount' => $repaymentTotal,
            'repayment_principal_amount' => $principalAmount,
            'repayment_interest_amount' => $interestAmount,
            'opening_outstanding_balance_amount' => $opening,
            'closing_outstanding_balance_amount' => $closing,
            'repayment_source' => 'payroll',
            'payment_reference_number' => $run->payroll_number,
            'notes' => sprintf(
                'Payroll loan deduction for %02d/%d (run #%d)',
                (int) $run->payroll_month,
                (int) $run->payroll_year,
                (int) $run->id
            ),
            'created_by' => $processedBy !== '' ? $processedBy : null,
            'created_at' => now(),
        ]);

        $totalRepaid = round((float) $loan->total_repaid_amount + $repaymentTotal, 2);

        $loanUpdates = [
            'total_repaid_amount' => $totalRepaid,
            'outstanding_balance_amount' => $closing,
            'modified_by' => $processedBy !== '' ? $processedBy : $loan->modified_by,
            'modified_at' => now(),
        ];

        if ($closing <= 0) {
            $loanUpdates['loan_status'] = 'completed';
            $loanUpdates['is_active'] = false;
            $loanUpdates['effective_end_date'] = $loan->effective_end_date ?? $repaymentDate->toDateString();
        }

        $loan->update($loanUpdates);

        return true;
    }

    private function resolveOpeningBalance(EmployeeLoan $loan): float
    {
        if ($loan->outstanding_balance_amount !== null) {
            return max(0, (float) $loan->outstanding_balance_amount);
        }

        $principal = (float) $loan->principal_amount;
        $interest = (float) ($loan->total_interest_amount ?? 0);
        $repaid = (float) ($loan->total_repaid_amount ?? 0);

        return max(0, round($principal + $interest - $repaid, 2));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function splitRepaymentAmounts(EmployeeLoan $loan, float $repaymentTotal): array
    {
        $monthlyInterest = max(0, (float) ($loan->monthly_interest_amount ?? 0));
        $monthlyPrincipal = max(0, (float) ($loan->monthly_principal_amount ?? 0));

        if ($monthlyInterest + $monthlyPrincipal <= 0) {
            return [$repaymentTotal, 0.0];
        }

        if ($repaymentTotal >= $monthlyInterest + $monthlyPrincipal) {
            return [round($monthlyPrincipal, 2), round($monthlyInterest, 2)];
        }

        $interestAmount = round(min($monthlyInterest, $repaymentTotal), 2);
        $principalAmount = round($repaymentTotal - $interestAmount, 2);

        return [$principalAmount, $interestAmount];
    }

    private function resolvePayrollNumber(?string $payrollNumber): ?int
    {
        if ($payrollNumber === null || $payrollNumber === '') {
            return null;
        }

        return is_numeric($payrollNumber) ? (int) $payrollNumber : null;
    }
}
