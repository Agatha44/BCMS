<?php

namespace App\Console\Commands;

use App\Services\AttendanceViolationService;
use App\Models\Bms\AttendanceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckAttendanceViolations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:check-violations 
                            {--date= : Date to check violations for (Y-m-d format). Defaults to today}
                            {--yesterday : Check violations for yesterday}
                            {--all : Check violations for all dates with attendance logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for late arrivals and early departures based on shift schedules';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $date = null;
        $checkAll = $this->option('all');
        
        if ($checkAll) {
            return $this->checkAllDates();
        }
        
        if ($this->option('yesterday')) {
            $date = Carbon::yesterday()->format('Y-m-d');
        } elseif ($this->option('date')) {
            $date = $this->option('date');
        }

        $this->info("Checking attendance violations" . ($date ? " for date: {$date}" : " for today"));
        
        Log::info('Attendance violation check started', [
            'date' => $date ?? Carbon::today()->format('Y-m-d'),
        ]);

        try {
            $service = new AttendanceViolationService();
            $results = $service->checkViolations($date);

            $this->displayResults($results);

            Log::info('Attendance violation check completed', [
                'date' => $results['date'],
                'late_arrivals_count' => count($results['late_arrivals']),
                'early_departures_count' => count($results['early_departures']),
                'errors_count' => count($results['errors']),
            ]);

            $this->newLine();
            $this->info("Violation check completed successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error checking violations: " . $e->getMessage());
            
            Log::error('Attendance violation check failed', [
                'date' => $date ?? Carbon::today()->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check violations for all dates with attendance logs
     */
    private function checkAllDates(): int
    {
        $this->info("Checking attendance violations for all dates with attendance logs...");
        
        try {
            // Get all unique dates from attendance_logs table
            $dates = AttendanceLog::whereNotNull('time_in')
                ->selectRaw('DATE(time_in) as date')
                ->distinct()
                ->orderBy('date', 'desc')
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('Y-m-d');
                })
                ->toArray();

            if (empty($dates)) {
                $this->warn("No attendance logs found in the database.");
                return Command::SUCCESS;
            }

            $this->info("Found " . count($dates) . " date(s) with attendance logs.");
            $this->newLine();

            $service = new AttendanceViolationService();
            $totalLateArrivals = 0;
            $totalEarlyDepartures = 0;
            $totalErrors = 0;
            $processedDates = 0;

            $progressBar = $this->output->createProgressBar(count($dates));
            $progressBar->start();

            foreach ($dates as $date) {
                try {
                    $results = $service->checkViolations($date);
                    
                    $totalLateArrivals += count($results['late_arrivals']);
                    $totalEarlyDepartures += count($results['early_departures']);
                    $totalErrors += count($results['errors']);
                    $processedDates++;

                    $progressBar->advance();
                } catch (\Exception $e) {
                    Log::error("Error checking violations for date: {$date}", [
                        'date' => $date,
                        'error' => $e->getMessage(),
                    ]);
                    $totalErrors++;
                }
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display summary
            $this->info("=== Summary ===");
            $this->info("Dates Processed: {$processedDates} / " . count($dates));
            $this->info("Total Late Arrivals: {$totalLateArrivals}");
            $this->info("Total Early Departures: {$totalEarlyDepartures}");
            if ($totalErrors > 0) {
                $this->warn("Total Errors: {$totalErrors}");
            }

            Log::info('Attendance violation check for all dates completed', [
                'total_dates' => count($dates),
                'processed_dates' => $processedDates,
                'total_late_arrivals' => $totalLateArrivals,
                'total_early_departures' => $totalEarlyDepartures,
                'total_errors' => $totalErrors,
            ]);

            $this->newLine();
            $this->info("Violation check completed successfully!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error checking violations for all dates: " . $e->getMessage());
            
            Log::error('Attendance violation check for all dates failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Display violation check results
     */
    private function displayResults(array $results): void
    {
        $this->info("Date: {$results['date']}");
        $this->info("Late Arrivals: " . count($results['late_arrivals']));
        $this->info("Early Departures: " . count($results['early_departures']));
        
        if (!empty($results['errors'])) {
            $this->warn("Errors: " . count($results['errors']));
        }

        // Display late arrivals
        if (!empty($results['late_arrivals'])) {
            $this->newLine();
            $this->info("Late Arrivals:");
            foreach ($results['late_arrivals'] as $violation) {
                $this->line("  - {$violation['employee_name']} (PF: {$violation['pf_number']}) - {$violation['minutes_late']} minutes late");
            }
        }

        // Display early departures
        if (!empty($results['early_departures'])) {
            $this->newLine();
            $this->info("Early Departures:");
            foreach ($results['early_departures'] as $violation) {
                $this->line("  - {$violation['employee_name']} (PF: {$violation['pf_number']}) - {$violation['minutes_early']} minutes early");
            }
        }

        // Display errors
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn("Errors:");
            foreach ($results['errors'] as $error) {
                $this->error("  - " . ($error['pf_number'] ?? 'Unknown') . ": " . ($error['error'] ?? 'Unknown error'));
            }
        }
    }
}

