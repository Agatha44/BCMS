<?php

namespace App\Console\Commands;

use App\Services\Payroll\MandatoryDeductionProvisioner;
use Illuminate\Console\Command;

class SyncMandatoryDeductions extends Command
{
    protected $signature = 'payroll:sync-mandatory-deductions
                            {--employee= : Sync mandatory deductions for a single employee national ID}
                            {--effective-start= : Effective start date (Y-m-d) for new records}';

    protected $description = 'Assign mandatory deduction types to active employees';

    public function handle(MandatoryDeductionProvisioner $provisioner): int
    {
        $nationalId = $this->option('employee');
        $effectiveStart = $this->option('effective-start');

        if ($nationalId) {
            $result = $provisioner->syncForEmployee($nationalId, $effectiveStart);
            $this->info("Employee {$nationalId}: created {$result['created']}, skipped {$result['skipped']}.");

            return Command::SUCCESS;
        }

        $result = $provisioner->syncForAllActiveEmployees($effectiveStart);
        $this->info("Processed {$result['employees']} employees.");
        $this->info("Created {$result['created']} deduction records, skipped {$result['skipped']}.");

        return Command::SUCCESS;
    }
}
