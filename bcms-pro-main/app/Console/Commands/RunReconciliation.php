<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunReconciliation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reconciliation:run 
                            {--dry-run : Run without making changes to see what would be updated}
                            {--table= : Run reconciliation for a specific table only (event_payment, incident_fine, overload_fine, top_up, bridge_bills)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run reconciliation to fix bill_gen_at timestamps that are after payment_date';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificTable = $this->option('table');

        $this->info('Starting reconciliation process...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE: No changes will be made to the database');
        }

        // Define tables to process
        $tables = [
            'event_payment' => 'EVENT PAYMENTS',
            'incident_fine' => 'INCIDENT FINES',
            'overload_fine' => 'OVERLOAD FINES',
            'top_up' => 'TOP-UP PAYMENTS',
            'bridge_bills' => 'BRIDGE BILLS (TBS + SHIFT CASES)',
        ];

        // If specific table requested, filter
        if ($specificTable) {
            if (!isset($tables[$specificTable])) {
                $this->error("Invalid table: {$specificTable}");
                $this->info('Valid tables: ' . implode(', ', array_keys($tables)));
                return Command::FAILURE;
            }
            $tables = [$specificTable => $tables[$specificTable]];
        }

        $totalUpdated = 0;
        $errors = [];

        foreach ($tables as $table => $description) {
            try {
                $this->line('');
                $this->info("Processing: {$description} ({$table})");

                // Check if table exists
                if (!$this->tableExists($table)) {
                    $this->warn("Table '{$table}' does not exist, skipping...");
                    continue;
                }

                // Check if columns exist
                if (!$this->columnsExist($table)) {
                    $this->warn("Required columns (payment_date, bill_gen_at) do not exist in '{$table}', skipping...");
                    continue;
                }

                // Count records that need fixing
                $countQuery = "SELECT COUNT(*) as count 
                               FROM bcmis.{$table} 
                               WHERE payment_date < bill_gen_at 
                               AND payment_date IS NOT NULL 
                               AND bill_gen_at IS NOT NULL";

                $count = DB::select($countQuery)[0]->count ?? 0;

                if ($count > 0) {
                    $this->comment("Found {$count} record(s) that need reconciliation");

                    if (!$dryRun) {
                        // Run the update
                        $rowsAffected = DB::table($table)
                            ->whereColumn('payment_date', '<', 'bill_gen_at')
                            ->whereNotNull('payment_date')
                            ->whereNotNull('bill_gen_at')
                            ->update([
                                'bill_gen_at' => DB::raw('DATE_SUB(payment_date, INTERVAL 20 SECOND)')
                            ]);

                        $this->info("✓ Updated {$rowsAffected} record(s)");
                        $totalUpdated += $rowsAffected;
                    } else {
                        // Show what would be updated
                        $sampleQuery = "SELECT id, payment_date, bill_gen_at 
                                       FROM bcmis.{$table} 
                                       WHERE payment_date < bill_gen_at 
                                       AND payment_date IS NOT NULL 
                                       AND bill_gen_at IS NOT NULL 
                                       LIMIT 5";

                        $samples = DB::select($sampleQuery);

                        if (!empty($samples)) {
                            $this->comment('Sample records that would be updated:');
                            foreach ($samples as $sample) {
                                $this->line("  ID: {$sample->id}, Payment: {$sample->payment_date}, Bill Gen: {$sample->bill_gen_at}");
                            }
                        }
                    }
                } else {
                    $this->comment("No records need reconciliation");
                }

            } catch (\Exception $e) {
                $errorMsg = "Error processing {$table}: " . $e->getMessage();
                $this->error($errorMsg);
                $errors[] = $errorMsg;
                Log::error('Reconciliation error for ' . $table, [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->line('');
        
        if (!empty($errors)) {
            $this->error('Reconciliation completed with errors:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn("Dry run completed. No changes were made.");
        } else {
            $this->info("✓ Reconciliation completed successfully!");
            $this->info("Total records updated: {$totalUpdated}");
        }

        return Command::SUCCESS;
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table
     * @return bool
     */
    private function tableExists(string $table): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE '{$table}'");
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if required columns exist in the table
     *
     * @param string $table
     * @return bool
     */
    private function columnsExist(string $table): bool
    {
        try {
            $columns = DB::select("SHOW COLUMNS FROM bcmis.{$table} LIKE 'payment_date'");
            $hasPaymentDate = !empty($columns);

            $columns = DB::select("SHOW COLUMNS FROM bcmis.{$table} LIKE 'bill_gen_at'");
            $hasBillGenAt = !empty($columns);

            return $hasPaymentDate && $hasBillGenAt;
        } catch (\Exception $e) {
            return false;
        }
    }
}

