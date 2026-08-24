<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Receipts\ZReportController;
use App\Models\ZreportProcessingJob;
use Illuminate\Http\Request;

class ProcessAllMissingZreports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zreport:process-all-missing 
                            {--start-date=20210324 : Start date in YYYYMMDD format}
                            {--end-date=20250930 : End date in YYYYMMDD format}
                            {--batch-size=1 : Number of dates to process per request}
                            {--test-mode=true : Run in test mode (true) or live mode (false)}
                            {--delay=2 : Delay in seconds between requests}
                            {--max-iterations=1000 : Maximum number of iterations to prevent infinite loops}
                            {--use-api : Use HTTP API calls instead of direct controller calls}
                            {--job-id= : Job ID for status tracking (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically process all missing Z-reports by calling the API endpoint repeatedly until all are processed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $batchSize = (int)$this->option('batch-size');
        $testMode = filter_var($this->option('test-mode'), FILTER_VALIDATE_BOOLEAN);
        $delay = (int)$this->option('delay');
        $maxIterations = (int)$this->option('max-iterations');
        $useApi = $this->option('use-api');
        $jobId = $this->option('job-id');

        // Load job if job-id provided
        $job = null;
        if ($jobId) {
            $job = ZreportProcessingJob::where('job_id', $jobId)->first();
            if ($job) {
                $job->status = ZreportProcessingJob::STATUS_RUNNING;
                $job->started_at = now();
                $job->save();
            }
        }

        $this->info('Starting automatic processing of missing Z-reports...');
        $this->info("Configuration:");
        $this->line("  Start Date: {$startDate}");
        $this->line("  End Date: {$endDate}");
        $this->line("  Batch Size: {$batchSize}");
        $this->line("  Test Mode: " . ($testMode ? 'Yes' : 'No'));
        $this->line("  Delay between requests: {$delay} seconds");
        $this->newLine();

        // Skip confirmation if running from API (has job_id) - it's non-interactive background process
        // Only ask for confirmation if running manually (no job_id) and not in test mode
        if (!$testMode && !$jobId) {
            try {
                if (!$this->confirm('⚠️  LIVE MODE: This will actually post Z-reports to TRA. Continue?', false)) {
                    $this->warn('Operation cancelled.');
                    return 1;
                }
            } catch (\Exception $e) {
                // Non-interactive mode (background/nohup), proceed without confirmation
                $this->warn('⚠️  LIVE MODE: Running in non-interactive mode. Proceeding without confirmation.');
            }
        }
        
        if (!$testMode && $jobId) {
            $this->warn('⚠️  LIVE MODE: Posting Z-reports to TRA (running from API/background)');
        }

        $iteration = 0;
        $totalProcessed = 0;
        $totalSuccessful = 0;
        $totalFailed = 0;
        $offset = 0;
        $startTime = microtime(true);

        // Prepare initial request
        $requestData = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'batch_size' => $batchSize,
            'test_mode' => $testMode,
            'offset' => $offset
        ];
        
        // Add job_id if provided
        if ($jobId) {
            $requestData['job_id'] = $jobId;
        }

        while ($iteration < $maxIterations) {
            // Check if job was cancelled
            if ($job) {
                $job->refresh();
                if ($job->status === ZreportProcessingJob::STATUS_CANCELLED) {
                    $this->warn("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    $this->warn("⚠️  Job cancelled by user. Stopping processing.");
                    $this->warn("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    break;
                }
            }
            
            $iteration++;
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("Iteration #{$iteration}");
            $this->line("Request: " . json_encode($requestData, JSON_PRETTY_PRINT));

            try {
                // Use direct controller call (faster) or HTTP API call
                if ($useApi) {
                    // Make HTTP API request
                    $apiUrl = config('app.url') . '/api/z-report/process-all-missing';
                    $this->line("Calling API: {$apiUrl}");
                    
                    $response = Http::timeout(300) // 5 minutes timeout
                        ->post($apiUrl, $requestData);

                    if (!$response->successful()) {
                        $this->error("API request failed with status: " . $response->status());
                        $this->error("Response: " . $response->body());
                        break;
                    }

                    $data = $response->json();
                } else {
                    // Direct controller call (faster, no HTTP overhead)
                    $this->line("Calling controller directly...");
                    
                    $controller = new ZReportController();
                    $request = Request::create('/api/z-report/process-all-missing', 'POST', $requestData);
                    $response = $controller->processAllMissingZreports($request);
                    $data = json_decode($response->getContent(), true);
                }

                if (!isset($data['status']) || $data['status'] != 1) {
                    $this->error("API returned error: " . ($data['message'] ?? 'Unknown error'));
                    $this->error("Response: " . json_encode($data, JSON_PRETTY_PRINT));
                    break;
                }

                // Display results
                $summary = $data['data']['summary'] ?? [];
                $processed = $summary['processed_in_this_batch'] ?? 0;
                $successful = $summary['successful'] ?? 0;
                $failed = $summary['failed'] ?? 0;
                $remaining = $summary['remaining'] ?? 0;
                $totalMissing = $summary['total_missing'] ?? 0;

                $totalProcessed += $processed;
                $totalSuccessful += $successful;
                $totalFailed += $failed;

                // Update job status if tracking
                if ($job) {
                    $progress = $totalMissing > 0 ? (($totalMissing - $remaining) / $totalMissing) * 100 : 0;
                    $currentDate = null;
                    if (isset($data['data']['processed_dates']) && !empty($data['data']['processed_dates'])) {
                        $lastDate = end($data['data']['processed_dates']);
                        $currentDate = $lastDate['date'] ?? null;
                    }
                    
                    $job->total_missing = $totalMissing;
                    $job->total_processed = $totalProcessed;
                    $job->total_successful = $totalSuccessful;
                    $job->total_failed = $totalFailed;
                    $job->remaining = $remaining;
                    $job->current_offset = $summary['current_offset'] ?? $offset;
                    $job->progress_percentage = $progress;
                    $job->current_date_processing = $currentDate;
                    $job->last_response = $data['data'] ?? null;
                    $job->save();
                }

                $this->info("✓ Response received");
                $this->line("  Processed in this batch: {$processed}");
                $this->line("  Successful: {$successful}");
                $this->line("  Failed: {$failed}");
                $this->line("  Remaining: {$remaining}");
                $this->line("  Total Missing: {$totalMissing}");

                // Show progress bar
                if ($totalMissing > 0) {
                    $progress = (($totalMissing - $remaining) / $totalMissing) * 100;
                    $this->line("  Progress: " . number_format($progress, 2) . "%");
                }

                // Check if done
                $continuation = $data['data']['continuation'] ?? [];
                $hasMore = $continuation['has_more'] ?? false;

                if (!$hasMore || $remaining == 0) {
                    // Update job as completed
                    if ($job) {
                        $job->status = ZreportProcessingJob::STATUS_COMPLETED;
                        $job->completed_at = now();
                        $job->progress_percentage = 100;
                        $job->remaining = 0;
                        $job->save();
                    }
                    
                    $this->newLine();
                    $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                    $this->info("✅ ALL DONE! All missing Z-reports have been processed.");
                    $this->newLine();
                    break;
                }

                // Prepare next request
                $nextRequest = $continuation['next_request_example'] ?? null;
                if ($nextRequest) {
                    $requestData = $nextRequest;
                    $offset = $nextRequest['offset'] ?? ($offset + $batchSize);
                } else {
                    // Fallback: manually increment offset
                    $offset += $batchSize;
                    $requestData['offset'] = $offset;
                }
                
                // Ensure job_id is included in next request
                if ($jobId) {
                    $requestData['job_id'] = $jobId;
                }

                // Show processed dates if available
                $processedDates = $data['data']['processed_dates'] ?? [];
                if (!empty($processedDates)) {
                    $this->line("  Processed dates:");
                    foreach ($processedDates as $dateInfo) {
                        $status = $dateInfo['status'] ?? 'unknown';
                        $date = $dateInfo['date'] ?? 'unknown';
                        $statusIcon = $status === 'success' ? '✓' : '✗';
                        $this->line("    {$statusIcon} {$date} ({$status})");
                    }
                }

                // Delay before next request
                if ($hasMore && $delay > 0) {
                    $this->line("  Waiting {$delay} seconds before next request...");
                    sleep($delay);
                }

            } catch (\Exception $e) {
                // Update job as failed
                if ($job) {
                    $job->status = ZreportProcessingJob::STATUS_FAILED;
                    $job->error_message = $e->getMessage();
                    $job->completed_at = now();
                    $job->save();
                }
                
                $this->error("Exception occurred: " . $e->getMessage());
                $this->error("Stack trace: " . $e->getTraceAsString());
                Log::error('Process All Missing Z-Reports Command: Error', [
                    'iteration' => $iteration,
                    'job_id' => $jobId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                break;
            }
        }

        // Final summary
        $totalTime = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("FINAL SUMMARY");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Total Iterations: {$iteration}");
        $this->line("Total Processed: {$totalProcessed}");
        $this->line("Total Successful: {$totalSuccessful}");
        $this->line("Total Failed: {$totalFailed}");
        $this->line("Total Time: {$totalTime} seconds");
        $this->line("Average Time per Batch: " . ($iteration > 0 ? round($totalTime / $iteration, 2) : 0) . " seconds");
        $this->newLine();

        if ($iteration >= $maxIterations) {
            // Update job status
            if ($job) {
                $job->status = ZreportProcessingJob::STATUS_FAILED;
                $job->error_message = "Reached maximum iterations limit ({$maxIterations})";
                $job->completed_at = now();
                $job->save();
            }
            
            $this->warn("⚠️  Reached maximum iterations limit ({$maxIterations}). There may be more dates to process.");
            $this->warn("   Run the command again to continue processing.");
            return 1;
        }

        // Final job update if completed successfully
        if ($job && $iteration < $maxIterations) {
            $job->status = ZreportProcessingJob::STATUS_COMPLETED;
            $job->completed_at = now();
            $job->progress_percentage = 100;
            $job->save();
        }

        return 0;
    }
}

