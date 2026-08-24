<?php

namespace App\Console\Commands;

use App\Services\AttendanceLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AttendanceSyncService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:sync-service 
                            {--interval=120 : Interval in seconds between syncs (default: 120 = 2 minutes)}
                            {--minutes=5 : Number of minutes to look back for recent logs}
                            {--once : Run once and exit (for testing)}
                            {--all : Sync all logs from device (not just recent)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Windows Service for continuous attendance auto-sync from biometric device';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $interval = (int) $this->option('interval');
        $minutes = (int) $this->option('minutes');
        $runOnce = $this->option('once');
        $syncAll = $this->option('all');

        $this->info("Attendance Sync Service Started");
        if ($syncAll) {
            $this->info("Mode: Sync ALL logs from device");
        } else {
            $this->info("Sync interval: {$interval} seconds");
            $this->info("Looking back: {$minutes} minutes");
        }
        $this->info("Press Ctrl+C to stop");

        Log::info('Attendance Sync Service started', [
            'interval' => $interval,
            'minutes_look_back' => $minutes,
            'run_once' => $runOnce
        ]);

        do {
            try {
                $this->info("[" . now()->format('Y-m-d H:i:s') . "] Starting sync...");
                
                // Use the service directly (no queue needed for Windows service)
                $attendanceService = new AttendanceLogService();
                
                if ($syncAll) {
                    $result = $attendanceService->syncAllLogs(true);
                } else {
                    $result = $attendanceService->syncRecentLogs($minutes, true);
                }

                if ($result['success']) {
                    $this->info("[" . now()->format('Y-m-d H:i:s') . "] Sync completed: {$result['synced']} synced, {$result['skipped']} skipped, {$result['errors']} errors");
                    
                    Log::info('Attendance sync service completed', [
                        'synced' => $result['synced'],
                        'skipped' => $result['skipped'],
                        'errors' => $result['errors'],
                        'total_sessions' => $result['total_sessions'] ?? 0
                    ]);
                } else {
                    $this->warn("[" . now()->format('Y-m-d H:i:s') . "] Sync failed: {$result['message']}");
                    
                    Log::warning('Attendance sync service failed', [
                        'message' => $result['message'],
                        'errors' => $result['errors']
                    ]);
                }

            } catch (\Exception $e) {
                $this->error("[" . now()->format('Y-m-d H:i:s') . "] Error: " . $e->getMessage());
                
                Log::error('Attendance sync service error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            if ($runOnce) {
                break;
            }

            // Wait for the specified interval
            $this->info("[" . now()->format('Y-m-d H:i:s') . "] Waiting {$interval} seconds until next sync...");
            sleep($interval);

        } while (!$runOnce);

        $this->info("Attendance Sync Service Stopped");
        Log::info('Attendance Sync Service stopped');

        return Command::SUCCESS;
    }
}

