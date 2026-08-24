<?php

namespace App\Services\Payroll;

use App\Constants\EmployeeStatus;
use App\Models\Bms\BridgeEmployee;
use App\Models\Bms\Payroll\BenefitType;
use App\Models\Bms\Payroll\DeductionType;
use App\Models\Bms\Payroll\EmployeeBenefit;
use App\Models\Bms\Payroll\EmployeeDeduction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MandatoryDeductionProvisioner
{
    /**
     * Assign all mandatory deduction types to one employee.
     *
     * @return array{created: int, skipped: int}
     */
    public function syncForEmployee(string $nationalId, ?string $effectiveStartDate = null): array
    {
        $employee = BridgeEmployee::where('national_id', $nationalId)->first();
        if (! $employee || ! $this->isActiveEmployee($employee)) {
            return ['created' => 0, 'skipped' => 0];
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->mandatoryDeductionTypes() as $type) {
            if (! $this->employeeMatchesTypeScope($employee, $type)) {
                $skipped++;
                continue;
            }

            if ($this->upsertEmployeeDeduction($employee, $type, $effectiveStartDate)) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return compact('created', 'skipped');
    }

    /**
     * Assign mandatory deductions to all active employees.
     *
     * @return array{employees: int, created: int, skipped: int}
     */
    public function syncForAllActiveEmployees(?string $effectiveStartDate = null): array
    {
        $employees = $this->activeEmployees();
        $created = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $result = $this->syncForEmployee($employee->national_id, $effectiveStartDate);
            $created += $result['created'];
            $skipped += $result['skipped'];
        }

        return [
            'employees' => $employees->count(),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Recalculate percentage-based deductions and benefits for one employee.
     *
     * @return array{deductions_updated: int, benefits_updated: int}
     */
    public function recalculateForEmployee(string $nationalId): array
    {
        $employee = BridgeEmployee::where('national_id', $nationalId)->first();
        if (! $employee) {
            return ['deductions_updated' => 0, 'benefits_updated' => 0];
        }

        $deductionsUpdated = 0;
        $benefitsUpdated = 0;

        $deductions = EmployeeDeduction::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->get();

        foreach ($deductions as $deduction) {
            $type = DeductionType::find($deduction->deduction_type_id);
            if (! $type || $type->calculation_type !== 'percentage') {
                continue;
            }

            if ($this->updateDeductionAmounts($deduction, $employee, $type)) {
                $deductionsUpdated++;
            }
        }

        $benefits = EmployeeBenefit::query()
            ->where('employee_national_id', $nationalId)
            ->active()
            ->get();

        foreach ($benefits as $benefit) {
            $type = BenefitType::find($benefit->benefit_type_id);
            if (! $type || $type->calculation_type !== 'percentage') {
                continue;
            }

            if ($this->updateBenefitAmount($benefit, $employee, $type)) {
                $benefitsUpdated++;
            }
        }

        return [
            'deductions_updated' => $deductionsUpdated,
            'benefits_updated' => $benefitsUpdated,
        ];
    }

    /**
     * Recalculate all active employee deductions for a deduction type.
     *
     * @return array{updated: int}
     */
    public function recalculateForDeductionType(int $deductionTypeId): array
    {
        $type = DeductionType::find($deductionTypeId);
        if (! $type) {
            return ['updated' => 0];
        }

        $updated = 0;

        EmployeeDeduction::query()
            ->where('deduction_type_id', $deductionTypeId)
            ->active()
            ->chunkById(100, function ($deductions) use ($type, &$updated) {
                foreach ($deductions as $deduction) {
                    $employee = BridgeEmployee::where('national_id', $deduction->employee_national_id)->first();
                    if (! $employee) {
                        continue;
                    }

                    if ($this->updateDeductionAmounts($deduction, $employee, $type, syncPercentagesFromType: (bool) $type->is_mandatory)) {
                        $updated++;
                    }
                }
            }, 'employee_deduction_id');

        return ['updated' => $updated];
    }

    /**
     * Recalculate all active employee benefits for a benefit type.
     *
     * @return array{updated: int}
     */
    public function recalculateForBenefitType(int $benefitTypeId): array
    {
        $type = BenefitType::find($benefitTypeId);
        if (! $type || $type->calculation_type !== 'percentage') {
            return ['updated' => 0];
        }

        $updated = 0;

        EmployeeBenefit::query()
            ->where('benefit_type_id', $benefitTypeId)
            ->active()
            ->chunkById(100, function ($benefits) use ($type, &$updated) {
                foreach ($benefits as $benefit) {
                    $employee = BridgeEmployee::where('national_id', $benefit->employee_national_id)->first();
                    if (! $employee) {
                        continue;
                    }

                    if ($this->updateBenefitAmount($benefit, $employee, $type)) {
                        $updated++;
                    }
                }
            }, 'employee_benefit_id');

        return ['updated' => $updated];
    }

    /**
     * @return array{
     *     employee_contribution_percentage: float,
     *     employer_contribution_percentage: float,
     *     employee_contribution_amount: float,
     *     employer_contribution_amount: float,
     *     total_deduction_amount: float
     * }
     */
    public function computeDeductionAmounts(BridgeEmployee $employee, DeductionType $type): array
    {
        $empPct = (float) ($type->employee_contribution_percentage ?? 0);
        $erPct = (float) ($type->employer_contribution_percentage ?? 0);
        $basicSalary = (float) ($employee->basicsalary ?? 0);

        if ($type->calculation_type === 'fixed') {
            $empAmount = round((float) ($type->calculation_value ?? 0), 2);
            $erAmount = 0.0;
        } else {
            $empAmount = round($basicSalary * $empPct / 100, 2);
            $erAmount = round($basicSalary * $erPct / 100, 2);
        }

        return [
            'employee_contribution_percentage' => $empPct,
            'employer_contribution_percentage' => $erPct,
            'employee_contribution_amount' => $empAmount,
            'employer_contribution_amount' => $erAmount,
            'total_deduction_amount' => round($empAmount + $erAmount, 2),
        ];
    }

    private function upsertEmployeeDeduction(
        BridgeEmployee $employee,
        DeductionType $type,
        ?string $effectiveStartDate = null
    ): bool {
        $exists = EmployeeDeduction::query()
            ->where('employee_national_id', $employee->national_id)
            ->where('deduction_type_id', $type->deduction_type_id)
            ->active()
            ->exists();

        if ($exists) {
            return false;
        }

        $amounts = $this->computeDeductionAmounts($employee, $type);
        $startDate = $effectiveStartDate ?? now()->startOfMonth()->toDateString();

        EmployeeDeduction::create([
            'employee_national_id' => $employee->national_id,
            'deduction_type_id' => $type->deduction_type_id,
            'total_deduction_amount' => $amounts['total_deduction_amount'],
            'employee_contribution_percentage' => $amounts['employee_contribution_percentage'],
            'employee_contribution_amount' => $amounts['employee_contribution_amount'],
            'employer_contribution_percentage' => $amounts['employer_contribution_percentage'],
            'employer_contribution_amount' => $amounts['employer_contribution_amount'],
            'is_before_tax' => (bool) $type->is_before_tax,
            'effective_start_date' => $startDate,
            'is_active' => true,
            'notes' => 'Auto-assigned mandatory deduction',
            'created_by' => $this->actorPfNumber(),
            'created_at' => now(),
        ]);

        Log::info('Mandatory deduction assigned', [
            'national_id' => $employee->national_id,
            'deduction_type_id' => $type->deduction_type_id,
            'deduction_code' => $type->deduction_code,
        ]);

        return true;
    }

    private function updateDeductionAmounts(
        EmployeeDeduction $deduction,
        BridgeEmployee $employee,
        DeductionType $type,
        bool $syncPercentagesFromType = false
    ): bool {
        if ($type->calculation_type === 'fixed') {
            $empAmount = round((float) ($type->calculation_value ?? $deduction->employee_contribution_amount ?? 0), 2);
            $erAmount = round((float) ($deduction->employer_contribution_amount ?? 0), 2);
            $empPct = (float) ($deduction->employee_contribution_percentage ?? 0);
            $erPct = (float) ($deduction->employer_contribution_percentage ?? 0);
        } else {
            $empPct = $syncPercentagesFromType
                ? (float) ($type->employee_contribution_percentage ?? 0)
                : (float) ($deduction->employee_contribution_percentage ?? $type->employee_contribution_percentage ?? 0);
            $erPct = $syncPercentagesFromType
                ? (float) ($type->employer_contribution_percentage ?? 0)
                : (float) ($deduction->employer_contribution_percentage ?? $type->employer_contribution_percentage ?? 0);

            $basicSalary = (float) ($employee->basicsalary ?? 0);
            $empAmount = round($basicSalary * $empPct / 100, 2);
            $erAmount = round($basicSalary * $erPct / 100, 2);
        }

        $deduction->update([
            'employee_contribution_percentage' => $empPct,
            'employer_contribution_percentage' => $erPct,
            'employee_contribution_amount' => $empAmount,
            'employer_contribution_amount' => $erAmount,
            'total_deduction_amount' => round($empAmount + $erAmount, 2),
            'modified_by' => $this->actorPfNumber(),
            'modified_at' => now(),
        ]);

        return true;
    }

    private function updateBenefitAmount(
        EmployeeBenefit $benefit,
        BridgeEmployee $employee,
        BenefitType $type
    ): bool {
        $basicSalary = (float) ($employee->basicsalary ?? 0);
        $amount = round($basicSalary * (float) $type->calculation_value / 100, 2);

        $update = [
            'benefit_amount' => $amount,
            'modified_by' => $this->actorPfNumber(),
            'modified_at' => now(),
        ];

        if ($type->is_taxable) {
            $update['taxable_amount'] = $amount;
            $update['tax_free_amount'] = 0;
        } else {
            $update['taxable_amount'] = 0;
            $update['tax_free_amount'] = $amount;
        }

        $benefit->update($update);

        return true;
    }

    private function mandatoryDeductionTypes(): Collection
    {
        return DeductionType::query()
            ->where('is_mandatory', true)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    private function activeEmployees(): Collection
    {
        return BridgeEmployee::query()
            ->whereIn('employee_status', EmployeeStatus::activeValues())
            ->get();
    }

    private function isActiveEmployee(BridgeEmployee $employee): bool
    {
        return EmployeeStatus::isActive($employee->employee_status);
    }

    private function employeeMatchesTypeScope(BridgeEmployee $employee, DeductionType $type): bool
    {
        if ($type->contract_type && (string) $employee->emptype_id !== (string) $type->contract_type) {
            return false;
        }

        if ($type->department_section && (string) $employee->department_id !== (string) $type->department_section) {
            return false;
        }

        return true;
    }

    private function actorPfNumber(): string
    {
        return auth()->user()?->pf_number ?? 'SYSTEM';
    }
}
