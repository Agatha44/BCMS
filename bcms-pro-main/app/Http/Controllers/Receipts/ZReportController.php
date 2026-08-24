<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Helpers\ZReportService;
use App\Models\Transaction;
use App\Models\VfdRegistration;
use App\Models\Zreport;
use App\Models\ZreportRepostLog;
use App\Models\ZreportProcessingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class ZReportController extends Controller
{
    /**
     * Repost missing Z-reports for dates that have transactions but no Z-report
     *
     * POST payload:
     * {
     *     "start_date": "2024-01-01",  // Optional: defaults to 30 days ago
     *     "end_date": "2024-01-31",     // Optional: defaults to yesterday
     *     "limit": 10,                  // Optional: max number of dates to process (default: no limit)
     *     "test_mode": true             // Optional: true = dry run (default), false = actually post to TRA
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function repostMissingZreports(Request $request)
    {
        // Increase execution time limit for this operation
        set_time_limit(1800); // 30 minutes for large batches
        ini_set('max_execution_time', 1800);



        // Set headers early to prevent timeout (but response will be sent at end)
        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('X-Accel-Buffering: no'); // Disable nginx buffering
            header('Connection: keep-alive');
        }

        try {
            // Set date range defaults
            $startDate = $request->input('start_date');
            if (empty($startDate)) {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }

            $endDate = $request->input('end_date');
            if (empty($endDate)) {
                $endDate = date('Y-m-d', strtotime('-1 days'));
            }

            // Optional limit on number of dates to process
            // Default to 10 if not specified to prevent timeouts
            $limit = $request->input('limit');
            if ($limit !== null && is_numeric($limit)) {
                $limit = (int)$limit;
            } else {
                // Default limit to prevent timeouts on large date ranges
                $limit = 10;
            }

            // Test mode flag (default: true for safety)
            $testMode = $request->input('test_mode', true);
            if (!is_bool($testMode)) {
                $testMode = filter_var($testMode, FILTER_VALIDATE_BOOLEAN);
            }

            // Get VFD registration data once
            $vfdData = DB::table('vfd_registration')
                ->select([
                    'name',
                    'city',
                    'mobile',
                    'address',
                    'username',
                    'uin',
                    'vrn',
                    'password',
                    'receiptcode',
                    'tin',
                    'reg_id',
                    'serila',
                    'routingkey',
                    'taxoffice',
                    DB::raw('DATE(created_at) as date')
                ])
                ->orderBy('id', 'desc')
                ->first();

            if (!$vfdData) {
                return response()->json([
                    'status' => 0,
                    'message' => 'VFD registration data not found',
                    'data' => []
                ], 404);
            }

            // Convert to array for easier handling
            $vfdDataArray = (array)$vfdData;

            // Always use optimized database connection for read-heavy transaction queries
            // This connection points to the optimized/backup database with proper indexes
            // No fallback - connection failure will stop processing and report error
            try {
                $optimizedDb = DB::connection('mysql_optimized');
                // Test the connection
                $optimizedDb->select('SELECT 1');
                Log::info('Repost Missing Z-Reports: Using optimized database connection', [
                    'host' => config('database.connections.mysql_optimized.host'),
                    'database' => config('database.connections.mysql_optimized.database')
                ]);
            } catch (\Exception $connEx) {
                $errorMsg = 'Optimized database connection failed. Host: ' . config('database.connections.mysql_optimized.host') . 
                           ', Database: ' . config('database.connections.mysql_optimized.database') . 
                           '. Error: ' . $connEx->getMessage();
                
                Log::error('Repost Missing Z-Reports: Optimized connection failed', [
                    'error' => $connEx->getMessage(),
                    'host' => config('database.connections.mysql_optimized.host'),
                    'database' => config('database.connections.mysql_optimized.database')
                ]);
                
                // Update job status if job_id is provided
                $jobId = $request->input('job_id');
                if ($jobId) {
                    try {
                        $job = ZreportProcessingJob::where('job_id', $jobId)->first();
                        if ($job) {
                            $job->status = ZreportProcessingJob::STATUS_FAILED;
                            $job->error_message = $errorMsg;
                            $job->completed_at = now();
                            $job->save();
                        }
                    } catch (\Exception $jobEx) {
                        Log::error('Repost Missing Z-Reports: Failed to update job status', [
                            'error' => $jobEx->getMessage()
                        ]);
                    }
                }
                
                throw new \Exception($errorMsg);
            }

            // Find all dates with transactions in the date range (optimized: single query with counts)
            $transactionDatesWithCounts = $optimizedDb->table('transactions')
                ->select(DB::raw('DATE(created_at) as transaction_date'), DB::raw('COUNT(*) as transaction_count'))
                ->whereRaw("DATE(created_at) >= ?", [$startDate])
                ->whereRaw("DATE(created_at) <= ?", [$endDate])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy(DB::raw('DATE(created_at)'), 'ASC')
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => is_string($item->transaction_date) ? $item->transaction_date : $item->transaction_date->format('Y-m-d'),
                        'count' => $item->transaction_count
                    ];
                })
                ->toArray();

            // Extract just the dates for compatibility
            $transactionDates = array_column($transactionDatesWithCounts, 'date');

            if (empty($transactionDates)) {
                Log::info('Repost Missing Z-Reports: No transactions found', [
                    'date_range' => ['start' => $startDate, 'end' => $endDate]
                ]);
                return response()->json([
                    'status' => 0,
                    'message' => 'No transactions found in the specified date range',
                    'data' => []
                ], 404);
            }

            Log::info('Repost Missing Z-Reports: Found transaction dates', [
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'total_dates_with_transactions' => count($transactionDates),
                'transaction_dates' => $transactionDatesWithCounts
            ]);

            // Warn if processing many dates without explicit limit
            if (empty($request->input('limit')) && count($transactionDates) > 20) {
                Log::warning('Repost Missing Z-Reports: Large date range detected', [
                    'total_dates' => count($transactionDates),
                    'default_limit' => $limit,
                    'suggestion' => 'Consider using limit parameter to process in smaller batches to avoid timeouts'
                ]);
            }

            // Get existing Z-report dates from zreport table
            $existingZreportDates = DB::table('zreport')
                ->whereRaw("DATE(date) >= ?", [$startDate])
                ->whereRaw("DATE(date) <= ?", [$endDate])
                ->pluck('date')
                ->map(function ($date) {
                    return is_string($date) ? $date : $date->format('Y-m-d');
                })
                ->toArray();

            Log::info('Repost Missing Z-Reports: Found existing Z-report dates', [
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'total_existing_zreports' => count($existingZreportDates),
                'existing_zreport_dates' => $existingZreportDates
            ]);

            // Find missing dates with transaction counts
            // Use array_flip for O(1) lookup instead of O(n) in_array
            $existingZreportDatesMap = array_flip($existingZreportDates);
            $missingDates = [];
            $missingDatesWithCounts = [];
            foreach ($transactionDatesWithCounts as $txnDate) {
                $dateStr = $txnDate['date'];
                if (!isset($existingZreportDatesMap[$dateStr])) {
                    $missingDates[] = $dateStr;
                    $missingDatesWithCounts[] = [
                        'date' => $dateStr,
                        'transaction_count' => $txnDate['count']
                    ];
                }
            }

            // Log missing dates (unposted transactions)
            if (!empty($missingDates)) {
                Log::warning('Repost Missing Z-Reports: Found dates with transactions but no Z-reports', [
                    'date_range' => ['start' => $startDate, 'end' => $endDate],
                    'total_missing_dates' => count($missingDates),
                    'missing_dates_with_transaction_counts' => $missingDatesWithCounts
                ]);
            }

            if (empty($missingDates)) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No missing Z-reports found. All dates have reports.',
                    'data' => [
                        'date_range' => [
                            'start' => $startDate,
                            'end' => $endDate
                        ],
                        'total_transaction_dates' => count($transactionDates),
                        'missing_dates' => []
                    ]
                ]);
            }

            // Apply limit if specified
            $totalMissing = count($missingDates);
            $limited = false;
            if ($limit !== null && $limit > 0 && count($missingDates) > $limit) {
                $missingDates = array_slice($missingDates, 0, $limit);
                $limited = true;
                Log::info('Repost Missing Z-Reports: Applied limit', [
                    'limit' => $limit,
                    'total_missing' => $totalMissing,
                    'will_process' => count($missingDates)
                ]);
            }

            Log::info('Repost Missing Z-Reports: Starting processing', [
                'test_mode' => $testMode,
                'total_missing_dates' => $totalMissing,
                'dates_to_process' => count($missingDates),
                'missing_dates' => $missingDates
            ]);

            // Process each missing date
            $results = [];
            $successCount = 0;
            $failureCount = 0;
            $startTime = microtime(true);

            // PERFORMANCE: Pre-fetch last Z-report date once to avoid repeated queries
            // This helps with cumulative calculation optimization
            $lastZreportDate = DB::table('zreport')
                ->select('date', 'gross')
                ->orderBy('date', 'desc')
                ->limit(1)
                ->first();

            foreach ($missingDates as $index => $missingDate) {
                $dateStartTime = microtime(true);

                Log::info('Repost Missing Z-Reports: Processing date ' . ($index + 1) . ' of ' . count($missingDates), [
                    'date' => $missingDate,
                    'index' => $index
                ]);

                // Add a small delay between processing dates to avoid overwhelming the API
                if ($index > 0 && !$testMode) {
                    usleep(500000); // 0.5 second delay between API calls
                }

                try {
                    // Generate parameters for this date
                    $date = $missingDate;
                    $time = '23:59:59'; // End of day time for historical reports
                    $znumber = date('Ymd', strtotime($missingDate));

                    Log::debug('Repost Missing Z-Reports: Fetching daily transaction data', [
                        'date' => $missingDate
                    ]);

                    // Optimized: Get all payment totals, count, and daily total in one query
                    // Use optimized database connection for better performance
                    $dailyData = $optimizedDb->table('transactions')
                        ->selectRaw("
                            COUNT(gc) as total_trans,
                            SUM(charged_amount) as daily_total,
                            SUM(CASE WHEN payment_type = 'CASH' THEN charged_amount ELSE 0 END) as cash_total,
                            SUM(CASE WHEN payment_type = 'EMONEY' THEN charged_amount ELSE 0 END) as emoney_total,
                            SUM(ROUND(charged_amount / 1.18, 2)) as net_amount,
                            SUM(charged_amount - ROUND(charged_amount / 1.18, 2)) as tax_amount
                        ")
                        ->whereRaw("DATE(created_at) = ?", [$missingDate])
                        ->first();

                    $sumSalesPrevDay = $dailyData->daily_total ?? 0;
                    $cashTotal = $dailyData->cash_total ?? 0.00;
                    $emoneyTotal = $dailyData->emoney_total ?? 0.00;
                    $totalNoTrans = $dailyData->total_trans ?? 0;
                    $nettamount = $dailyData->net_amount ?? 0;
                    $taxamount = $dailyData->tax_amount ?? 0;

                    Log::debug('Repost Missing Z-Reports: Daily data fetched', [
                        'date' => $missingDate,
                        'transaction_count' => $totalNoTrans,
                        'daily_total' => $sumSalesPrevDay
                    ]);

                    // Always calculate cumulative total from all transactions (Method 2)
                    // This ensures accuracy by always calculating from source data
                    Log::info('Repost Missing Z-Reports: Calculating cumulative from all transactions', [
                        'date' => $missingDate,
                        'note' => 'Always calculating from transactions for accuracy'
                    ]);

                    try {
                        // Set query timeout on optimized connection (5 minutes = 300000 milliseconds)
                        // This gives enough time for large cumulative calculations
                        $optimizedDb->statement("SET SESSION max_execution_time = 300000"); // 5 minutes
                        $optimizedDb->statement("SET SESSION innodb_lock_wait_timeout = 300"); // 5 minutes for InnoDB locks

                        $queryStartTime = microtime(true);

                        Log::info('Repost Missing Z-Reports: Starting cumulative calculation', [
                            'date' => $missingDate,
                            'note' => 'This may take several minutes for large datasets'
                        ]);

                        // Calculate cumulative total from all transactions up to this date
                        // Using direct comparison with created_at index for better performance
                        $cumulativeResult = $optimizedDb->selectOne(
                            "SELECT SUM(charged_amount) as cumulative_total 
                             FROM transactions 
                             WHERE created_at <= ?",
                            [$missingDate . ' 23:59:59']
                        );

                        $queryTime = round(microtime(true) - $queryStartTime, 2);

                        $cumulativeTotal = $cumulativeResult->cumulative_total ?? 0;

                        $queryTime = round(microtime(true) - $queryStartTime, 2);

                        $cumulativeTotal = $cumulativeResult->cumulative_total ?? 0;

                        Log::info('Repost Missing Z-Reports: Cumulative calculation completed', [
                            'date' => $missingDate,
                            'cumulative_total' => $cumulativeTotal,
                            'query_time_seconds' => $queryTime,
                            'calculation_method' => 'sum_all_transactions',
                            'note' => 'Calculated from all transactions from beginning'
                        ]);

                        // If query still times out or returns null, handle gracefully
                        if ($cumulativeTotal === null) {
                            Log::error('Repost Missing Z-Reports: Cumulative calculation returned null', [
                                'date' => $missingDate,
                                'possible_cause' => 'Query timeout or no transactions found'
                            ]);
                            throw new \Exception("Cumulative calculation returned null - possible timeout or no transactions found before this date");
                        }
                        
                        // Warn if cumulative is 0 but we have daily transactions (might indicate an issue)
                        if ($cumulativeTotal == 0 && $sumSalesPrevDay > 0) {
                            Log::warning('Repost Missing Z-Reports: Cumulative is 0 but daily total exists', [
                                'date' => $missingDate,
                                'daily_total' => $sumSalesPrevDay,
                                'note' => 'This might indicate the first Z-report or a data issue'
                            ]);
                        }
                        
                    } catch (\Exception $queryEx) {
                        $errorMsg = $queryEx->getMessage();
                        $isTimeout = strpos($errorMsg, 'maximum statement execution time exceeded') !== false || 
                                    strpos($errorMsg, 'Query execution was interrupted') !== false;
                        
                        if ($isTimeout) {
                            Log::error('Repost Missing Z-Reports: Cumulative calculation timed out', [
                                'date' => $missingDate,
                                'error' => $errorMsg,
                                'suggestion' => 'Query exceeded 5 minute timeout. Consider processing dates in smaller batches or optimizing database indexes on created_at column.'
                            ]);
                            
                            $errorMsg = "Cumulative calculation timed out for date: {$missingDate}. ";
                            $errorMsg .= "The query exceeded the 5 minute execution limit. ";
                            $errorMsg .= "This usually happens with very large datasets. ";
                            $errorMsg .= "Suggestions: 1) Ensure proper indexes on transactions.created_at column, ";
                            $errorMsg .= "2) Process dates chronologically from earliest to latest, ";
                            $errorMsg .= "3) Consider increasing database server resources or query timeout.";
                        } else {
                            Log::error('Repost Missing Z-Reports: Cumulative calculation query failed', [
                                'date' => $missingDate,
                                'error' => $errorMsg,
                                'suggestion' => 'Check database connection and ensure indexes are optimized'
                            ]);
                            
                            $errorMsg = "Cumulative calculation failed for date: {$missingDate}. Error: {$errorMsg}";
                        }
                        
                        throw new \Exception($errorMsg);
                    }

                    // Log transaction details for this date
                    Log::info('Repost Missing Z-Reports: All data calculated for date', [
                        'date' => $missingDate,
                        'znumber' => $znumber,
                        'transaction_count' => $totalNoTrans,
                        'daily_total' => $sumSalesPrevDay,
                        'cumulative_total' => $cumulativeTotal,
                        'cash_total' => $cashTotal,
                        'emoney_total' => $emoneyTotal,
                        'net_amount' => $nettamount,
                        'tax_amount' => $taxamount,
                        'test_mode' => $testMode
                    ]);

                    // Post the Z-Report (or skip if in test mode)
                    $resp = null;
                    if (!$testMode) {
                        Log::info('Repost Missing Z-Reports: Posting Z-Report to TRA API', [
                            'date' => $missingDate,
                            'znumber' => $znumber
                        ]);
                        $resp = ZReportService::postZReport(
                            $vfdDataArray,
                            $date,
                            $time,
                            $znumber,
                            $sumSalesPrevDay,
                            $cumulativeTotal,
                            $totalNoTrans,
                            $nettamount,
                            $taxamount,
                            $emoneyTotal,
                            $cashTotal
                        );

                        // Log successful post with all data and save to database
                        if (isset($resp['status']) && $resp['status'] == 1) {
                            // Save to database
                            try {
                                ZreportRepostLog::create([
                                    'report_date' => $missingDate,
                                    'znumber' => $znumber,
                                    'report_time' => $time,
                                    'vfd_name' => $vfdDataArray['name'] ?? null,
                                    'tin' => $vfdDataArray['tin'] ?? null,
                                    'vrn' => $vfdDataArray['vrn'] ?? null,
                                    'reg_id' => $vfdDataArray['reg_id'] ?? null,
                                    'daily_total' => $sumSalesPrevDay,
                                    'cumulative_total' => $cumulativeTotal,
                                    'net_amount' => $nettamount,
                                    'tax_amount' => $taxamount,
                                    'cash_total' => $cashTotal,
                                    'emoney_total' => $emoneyTotal,
                                    'transaction_count' => $totalNoTrans,
                                    'tra_status' => $resp['status'] ?? null,
                                    'tra_ackcode' => $resp['data']['ackcode'] ?? null,
                                    'tra_ackmsg' => $resp['data']['ackmsg'] ?? null,
                                    'tra_received_date' => $resp['data']['received_date'] ?? null,
                                    'tra_received_time' => $resp['data']['received_time'] ?? null,
                                    'test_mode' => false,
                                    'tra_response_data' => json_encode($resp),
                                    'processing_status' => 'success',
                                    'processing_time_seconds' => $dateProcessingTime,
                                    'posted_at' => now()
                                ]);
                            } catch (\Exception $saveEx) {
                                Log::error('Repost Missing Z-Reports: Failed to save to database', [
                                    'date' => $missingDate,
                                    'error' => $saveEx->getMessage()
                                ]);
                            }

                            Log::info('Repost Missing Z-Reports: Successfully posted to TRA', [
                                'date' => $missingDate,
                                'znumber' => $znumber,
                                'time' => $time,
                                'vfd_data' => [
                                    'name' => $vfdDataArray['name'] ?? null,
                                    'tin' => $vfdDataArray['tin'] ?? null,
                                    'vrn' => $vfdDataArray['vrn'] ?? null,
                                    'reg_id' => $vfdDataArray['reg_id'] ?? null
                                ],
                                'financial_data' => [
                                    'daily_total' => $sumSalesPrevDay,
                                    'cumulative_total' => $cumulativeTotal,
                                    'net_amount' => $nettamount,
                                    'tax_amount' => $taxamount,
                                    'cash_total' => $cashTotal,
                                    'emoney_total' => $emoneyTotal
                                ],
                                'transaction_data' => [
                                    'total_transactions' => $totalNoTrans,
                                    'transaction_date' => $missingDate
                                ],
                                'tra_response' => [
                                    'status' => $resp['status'] ?? null,
                                    'message' => $resp['message'] ?? null,
                                    'ackcode' => $resp['data']['ackcode'] ?? null,
                                    'ackmsg' => $resp['data']['ackmsg'] ?? null,
                                    'received_date' => $resp['data']['received_date'] ?? null,
                                    'received_time' => $resp['data']['received_time'] ?? null
                                ],
                                'posted_at' => now()->toDateTimeString()
                            ]);
                        } else {
                            // Save failed attempt to database
                            try {
                                ZreportRepostLog::create([
                                    'report_date' => $missingDate,
                                    'znumber' => $znumber,
                                    'report_time' => $time,
                                    'vfd_name' => $vfdDataArray['name'] ?? null,
                                    'tin' => $vfdDataArray['tin'] ?? null,
                                    'vrn' => $vfdDataArray['vrn'] ?? null,
                                    'reg_id' => $vfdDataArray['reg_id'] ?? null,
                                    'daily_total' => $sumSalesPrevDay,
                                    'cumulative_total' => $cumulativeTotal,
                                    'net_amount' => $nettamount,
                                    'tax_amount' => $taxamount,
                                    'cash_total' => $cashTotal,
                                    'emoney_total' => $emoneyTotal,
                                    'transaction_count' => $totalNoTrans,
                                    'tra_status' => $resp['status'] ?? null,
                                    'test_mode' => false,
                                    'tra_response_data' => json_encode($resp),
                                    'error_message' => $resp['message'] ?? 'Unknown error',
                                    'processing_status' => 'failed',
                                    'processing_time_seconds' => $dateProcessingTime,
                                    'posted_at' => now()
                                ]);
                            } catch (\Exception $saveEx) {
                                Log::error('Repost Missing Z-Reports: Failed to save failed attempt to database', [
                                    'date' => $missingDate,
                                    'error' => $saveEx->getMessage()
                                ]);
                            }

                            Log::warning('Repost Missing Z-Reports: Post to TRA failed or returned error', [
                                'date' => $missingDate,
                                'znumber' => $znumber,
                                'response' => $resp
                            ]);
                        }
                    } else {
                        // Save test mode data to database
                        try {
                            ZreportRepostLog::create([
                                'report_date' => $missingDate,
                                'znumber' => $znumber,
                                'report_time' => $time,
                                'vfd_name' => $vfdDataArray['name'] ?? null,
                                'tin' => $vfdDataArray['tin'] ?? null,
                                'vrn' => $vfdDataArray['vrn'] ?? null,
                                'reg_id' => $vfdDataArray['reg_id'] ?? null,
                                'daily_total' => $sumSalesPrevDay,
                                'cumulative_total' => $cumulativeTotal,
                                'net_amount' => $nettamount,
                                'tax_amount' => $taxamount,
                                'cash_total' => $cashTotal,
                                'emoney_total' => $emoneyTotal,
                                'transaction_count' => $totalNoTrans,
                                'test_mode' => true,
                                'processing_status' => 'pending',
                                'processing_time_seconds' => $dateProcessingTime,
                                'error_message' => 'Test mode - not posted to TRA'
                            ]);
                        } catch (\Exception $saveEx) {
                            Log::error('Repost Missing Z-Reports: Failed to save test mode data to database', [
                                'date' => $missingDate,
                                'error' => $saveEx->getMessage()
                            ]);
                        }

                        Log::info('Repost Missing Z-Reports: Test mode - data prepared but not posted', [
                            'date' => $missingDate,
                            'znumber' => $znumber,
                            'prepared_data' => [
                                'daily_total' => $sumSalesPrevDay,
                                'cumulative_total' => $cumulativeTotal,
                                'transaction_count' => $totalNoTrans,
                                'net_amount' => $nettamount,
                                'tax_amount' => $taxamount,
                                'cash_total' => $cashTotal,
                                'emoney_total' => $emoneyTotal
                            ]
                        ]);
                    }

                    // Calculate processing time for this date
                    $dateProcessingTime = round(microtime(true) - $dateStartTime, 3);

                    Log::info('Repost Missing Z-Reports: Date processing completed', [
                        'date' => $missingDate,
                        'processing_time_seconds' => $dateProcessingTime
                    ]);

                    // Record result
                    $resultEntry = [
                        'index' => $index + 1,
                        'date' => $missingDate,
                        'znumber' => $znumber,
                        'time' => $time,
                        'daily_total' => $sumSalesPrevDay,
                        'cumulative_total' => $cumulativeTotal,
                        'transaction_count' => $totalNoTrans,
                        'net_amount' => $nettamount,
                        'tax_amount' => $taxamount,
                        'cash_total' => $cashTotal,
                        'emoney_total' => $emoneyTotal,
                        'processing_time_seconds' => $dateProcessingTime,
                        'status' => $testMode ? 'pending_confirmation' : 'posted'
                    ];

                    // Add VFD data in test mode, response in live mode
                    if ($testMode) {
                        $resultEntry['vfd_data'] = [
                            'name' => $vfdDataArray['name'] ?? null,
                            'tin' => $vfdDataArray['tin'] ?? null,
                            'vrn' => $vfdDataArray['vrn'] ?? null,
                            'reg_id' => $vfdDataArray['reg_id'] ?? null
                        ];
                        $resultEntry['note'] = 'Data prepared but not sent - test mode enabled';
                    } else {
                        $resultEntry['response'] = $resp;
                    }

                    $results[] = $resultEntry;
                    $successCount++;

                } catch (\Exception $ex) {
                    $errorEntry = [
                        'date' => $missingDate,
                        'status' => 'failed',
                        'error' => $ex->getMessage()
                    ];
                    $results[] = $errorEntry;
                    $failureCount++;

                    Log::error('Z-Report failed for date: ' . $missingDate, [
                        'error' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString()
                    ]);
                }
            }

            // Calculate total execution time
            $totalExecutionTime = round(microtime(true) - $startTime, 3);
            $avgTimePerDate = $successCount > 0 ? round($totalExecutionTime / $successCount, 3) : 0;

            // Build message
            if ($testMode) {
                $message = "DRY RUN - Data prepared for verification: {$successCount} dates processed, {$failureCount} failed. No data sent to TRA.";
            } else {
                $message = "LIVE MODE - Z-Reports posted: {$successCount} successful, {$failureCount} failed.";
            }

            if ($limited) {
                $message .= " (Limited to {$limit} out of {$totalMissing} missing dates)";
            }

            // Log final summary with all successfully posted data
            $successfulPosts = array_filter($results, function($item) {
                return isset($item['status']) &&
                       ($item['status'] === 'posted' || $item['status'] === 'pending_confirmation') &&
                       (!isset($item['error']) || empty($item['error']));
            });

            Log::info('Repost Missing Z-Reports: Processing completed', [
                'test_mode' => $testMode,
                'date_range' => ['start' => $startDate, 'end' => $endDate],
                'summary' => [
                    'total_missing_dates' => $limited ? $totalMissing : count($missingDates),
                    'processed_in_this_run' => count($missingDates),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'total_execution_time_seconds' => $totalExecutionTime,
                    'avg_time_per_date_seconds' => $avgTimePerDate,
                    'limited' => $limited
                ],
                'processed_dates' => $missingDates,
                'successfully_posted_zreports' => array_map(function($item) {
                    return [
                        'date' => $item['date'] ?? null,
                        'znumber' => $item['znumber'] ?? null,
                        'daily_total' => $item['daily_total'] ?? null,
                        'cumulative_total' => $item['cumulative_total'] ?? null,
                        'transaction_count' => $item['transaction_count'] ?? null,
                        'tra_response' => $item['response'] ?? null,
                        'posted_at' => now()->toDateTimeString()
                    ];
                }, $successfulPosts)
            ]);

            // Build response data
            Log::info('Repost Missing Z-Reports: Building response data', [
                'results_count' => count($results),
                'response_size_estimate' => strlen(json_encode($results))
            ]);
            $responseData = [
                'status' => 1,
                'message' => $message,
                'test_mode' => $testMode,
                'note' => $testMode
                    ? 'This is a test run. Review data and set "test_mode": false to actually post to TRA.'
                    : 'Live mode - data has been posted to TRA.',
                'data' => [
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ],
                    'summary' => [
                        'total_missing_dates' => $limited ? $totalMissing : count($missingDates),
                        'processed_in_this_run' => count($missingDates),
                        'successful' => $successCount,
                        'failed' => $failureCount,
                        'total_execution_time_seconds' => $totalExecutionTime,
                        'avg_time_per_date_seconds' => $avgTimePerDate,
                        'limited' => $limited
                    ],
                    'missing_dates_processed' => $missingDates,
                    'results' => $results
                ]
            ];

            // Return comprehensive summary with proper headers
            Log::info('Repost Missing Z-Reports: Preparing to send response', [
                'response_data_size' => strlen(json_encode($responseData)),
                'total_execution_time' => $totalExecutionTime
            ]);

            // CRITICAL: Ensure response is sent immediately to prevent timeout
            $response = response()->json($responseData, 200);

            // Add headers to prevent buffering and timeouts
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
            $response->headers->set('X-Accel-Buffering', 'no'); // Disable nginx buffering
            $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
            $response->headers->set('Connection', 'keep-alive');
            $response->headers->set('Keep-Alive', 'timeout=300'); // 5 minutes

            // Log before sending response
            Log::info('Repost Missing Z-Reports: Sending response', [
                'status_code' => 200,
                'has_results' => !empty($results),
                'response_size_bytes' => strlen(json_encode($responseData))
            ]);

            // Ensure all output buffers are flushed before returning
            // This helps prevent timeout issues
            if (ob_get_level() > 0) {
                ob_end_flush();
            }

            return $response;

        } catch (\Exception $ex) {
            Log::error('Failed to repost missing Z-reports', [
                'error' => $ex->getMessage(),
                'type' => get_class($ex),
                'trace' => $ex->getTraceAsString()
            ]);

            $errorMessage = $ex->getMessage();
            if (strpos($errorMessage, 'timeout') !== false || strpos($errorMessage, 'Timeout') !== false) {
                $errorMessage = 'Request timed out. Try processing fewer dates at once by using the "limit" parameter.';
            }

            // Flush output buffers before sending error
            if (ob_get_level()) {
                ob_end_clean();
            }

            $errorResponse = response()->json([
                'status' => 0,
                'message' => 'Failed to repost missing Z-reports',
                'error' => $errorMessage,
                'error_type' => get_class($ex),
                'data' => []
            ], 500);

            // Add headers to prevent buffering
            $errorResponse->headers->set('Content-Type', 'application/json');
            $errorResponse->headers->set('X-Accel-Buffering', 'no');
            $errorResponse->headers->set('Connection', 'close');

            return $errorResponse;
        }
    }

    /**
     * Process all missing Z-reports using SQL query to find unposted dates
     * Processes them one by one automatically
     *
     * POST payload:
     * {
     *     "start_date": "20210324",  // Optional: defaults to 2021-03-24
     *     "end_date": "20250930",     // Optional: defaults to 2025-09-30
     *     "batch_size": 1,             // Optional: process N dates per request (default: 1)
     *     "test_mode": true            // Optional: true = dry run (default), false = actually post to TRA
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processAllMissingZreports(Request $request)
    {
        // Increase execution time limit for this operation
        set_time_limit(1800); // 30 minutes for large batches
        ini_set('max_execution_time', 1800);

        try {
            // Get date range from request or use defaults
            $startDateStr = $request->input('start_date', '20210324');
            $endDateStr = $request->input('end_date', '20250930');
            $batchSize = (int)$request->input('batch_size', 1);
            $testMode = $request->input('test_mode', true);
            if (!is_bool($testMode)) {
                $testMode = filter_var($testMode, FILTER_VALIDATE_BOOLEAN);
            }

            // Validate date format (should be YYYYMMDD)
            if (!preg_match('/^\d{8}$/', $startDateStr) || !preg_match('/^\d{8}$/', $endDateStr)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid date format. Use YYYYMMDD format (e.g., 20210324)',
                    'data' => []
                ], 400);
            }

            Log::info('Process All Missing Z-Reports: Starting', [
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
                'batch_size' => $batchSize,
                'test_mode' => $testMode
            ]);

            // Get offset/continuation parameter to track progress
            $offset = (int)$request->input('offset', 0);
            $lastProcessedDate = $request->input('last_processed_date'); // Optional: skip to a specific date
            $jobId = $request->input('job_id'); // Optional: job ID for status tracking
            
            // Load job if job-id provided
            $job = null;
            if ($jobId) {
                $job = ZreportProcessingJob::where('job_id', $jobId)->first();
            }

            // Use the SQL query to find all missing Z-reports
            // Exclude dates if znumber exists in:
            // 1. vfd_zreport table (already posted to TRA)
            // 2. zreport table (already exists locally, regardless of ackmsg or status)
            if ($testMode) {
                $missingDatesQuery = "
                    SELECT
                      DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y-%m-%d') AS missing_date,
                      DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') AS missing_znumber
                    FROM
                      (
                        SELECT
                          STR_TO_DATE(?, '%Y%m%d') AS start_date,
                          STR_TO_DATE(?, '%Y%m%d') AS end_date
                      ) d
                      JOIN (
                        SELECT
                          a.N + b.N * 10 + c.N * 100 + d.N * 1000 + e.N * 10000 AS n
                        FROM
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) e
                      ) n
                    WHERE
                      DATE_ADD(d.start_date, INTERVAL n.n DAY) <= d.end_date
                      -- Exclude if znumber exists in vfd_zreport (already posted to TRA)
                      AND DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') NOT IN (
                        SELECT znumber FROM vfd_zreport WHERE znumber IS NOT NULL
                      )
                      -- Exclude if znumber already exists in zreport table (already processed, regardless of ackmsg)
                      AND DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') NOT IN (
                        SELECT znumber FROM zreport WHERE znumber IS NOT NULL
                      )
                    ORDER BY missing_date
                ";
                $queryParams = [$startDateStr, $endDateStr];
            } else {
                $missingDatesQuery = "
                    SELECT
                      DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y-%m-%d') AS missing_date,
                      DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') AS missing_znumber
                    FROM
                      (
                        SELECT
                          STR_TO_DATE(?, '%Y%m%d') AS start_date,
                          STR_TO_DATE(?, '%Y%m%d') AS end_date
                      ) d
                      JOIN (
                        SELECT
                          a.N + b.N * 10 + c.N * 100 + d.N * 1000 + e.N * 10000 AS n
                        FROM
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d,
                          (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
                                  SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) e
                      ) n
                    WHERE
                      DATE_ADD(d.start_date, INTERVAL n.n DAY) <= d.end_date
                      -- Exclude if znumber exists in vfd_zreport (already posted to TRA)
                      AND DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') NOT IN (
                        SELECT znumber FROM vfd_zreport WHERE znumber IS NOT NULL
                      )
                      -- Exclude if znumber already exists in zreport table (already processed, regardless of ackmsg)
                      AND DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') NOT IN (
                        SELECT znumber FROM zreport WHERE znumber IS NOT NULL
                      )
                    ORDER BY missing_date
                ";
                $queryParams = [$startDateStr, $endDateStr];
            }

            // Get all missing dates first
            $allMissingDates = DB::select($missingDatesQuery, $queryParams);
            $totalMissing = count($allMissingDates);
            
            // Update job with total missing if tracking
            if ($job) {
                $job->total_missing = $totalMissing;
                $job->save();
            }
            
            if (empty($allMissingDates)) {
                // Update job as completed if no missing dates
                if ($job) {
                    $job->status = ZreportProcessingJob::STATUS_COMPLETED;
                    $job->completed_at = now();
                    $job->progress_percentage = 100;
                    $job->save();
                }
                return response()->json([
                    'status' => 1,
                    'message' => 'No missing Z-reports found in the specified date range.',
                    'data' => [
                        'start_date' => $startDateStr,
                        'end_date' => $endDateStr,
                        'total_missing' => 0,
                        'processed' => 0
                    ]
                ]);
            }

            // Apply offset and batch size
            $missingDates = array_slice($allMissingDates, $offset, $batchSize);
            Log::info('Process All Missing Z-Reports: Found missing dates', [
                'total_missing' => $totalMissing,
                'date_range' => ['start' => $startDateStr, 'end' => $endDateStr]
            ]);

            // Process dates in batches (one by one by default)
            $datesToProcess = array_slice($missingDates, 0, $batchSize);
            $processedDates = [];
            $successCount = 0;
            $failureCount = 0;
            $startTime = microtime(true);

            foreach ($datesToProcess as $dateInfo) {
                // Check if job was cancelled
                if ($job) {
                    $job->refresh();
                    if ($job->status === ZreportProcessingJob::STATUS_CANCELLED) {
                        Log::info('Process All Missing Z-Reports: Job cancelled, stopping processing', [
                            'job_id' => $jobId,
                            'processed_so_far' => $successCount + $failureCount
                        ]);
                        break;
                    }
                }
                
                $date = $dateInfo->missing_date;
                $znumber = $dateInfo->missing_znumber;

                Log::info('Process All Missing Z-Reports: Processing date', [
                    'date' => $date,
                    'znumber' => $znumber,
                    'progress' => ($successCount + $failureCount + 1) . ' of ' . min($batchSize, $totalMissing)
                ]);

                try {
                    // Call the existing repostMissingZreports logic for this single date
                    // We'll reuse the logic by calling it with a single date range
                    $dateRequest = new Request([
                        'start_date' => $date,
                        'end_date' => $date,
                        'limit' => 1,
                        'test_mode' => $testMode
                    ]);

                    // Process this single date using the existing method
                    $result = $this->processSingleDate($date, $testMode);

                    if ($result['success']) {
                        // Log successful processing with all data
                        Log::info('Process All Missing Z-Reports: Successfully processed date', [
                            'date' => $date,
                            'znumber' => $znumber,
                            'test_mode' => $testMode,
                            'processing_result' => [
                                'transaction_count' => $result['data']['transaction_count'] ?? 0,
                                'daily_total' => $result['data']['daily_total'] ?? 0,
                                'cumulative_total' => $result['data']['cumulative_total'] ?? 0,
                                'net_amount' => $result['data']['net_amount'] ?? 0,
                                'tax_amount' => $result['data']['tax_amount'] ?? 0,
                                'cash_total' => $result['data']['cash_total'] ?? 0,
                                'emoney_total' => $result['data']['emoney_total'] ?? 0,
                                'tra_response_status' => $result['data']['response']['status'] ?? ($testMode ? 'test_mode' : null),
                                'tra_ackcode' => $result['data']['response']['data']['ackcode'] ?? null,
                                'tra_ackmsg' => $result['data']['response']['data']['ackmsg'] ?? null
                            ]
                        ]);

                        // In test mode, save to zreport table to track that it's been processed
                        // This prevents reprocessing the same date in test mode
                        if ($testMode) {
                            try {
                                // Check if already exists
                                $existingZreport = DB::table('zreport')
                                    ->where('date', $date)
                                    ->where('znumber', $znumber)
                                    ->first();

                                if (!$existingZreport) {
                                    // Save a test mode record to track processing
                                    DB::table('zreport')->insert([
                                        'date' => $date,
                                        'time' => '23:59:59',
                                        'znumber' => (int)$znumber,
                                        'dailytotalamount' => $result['data']['daily_total'] ?? 0,
                                        'gross' => $result['data']['cumulative_total'] ?? 0,
                                        'ticketfiscal' => $result['data']['transaction_count'] ?? 0,
                                        'netamount' => $result['data']['net_amount'] ?? 0,
                                        'taxamount' => $result['data']['tax_amount'] ?? 0,
                                        'cashamount' => $result['data']['cash_total'] ?? 0,
                                        'emoney_amount' => $result['data']['emoney_total'] ?? 0,
                                        'ackcode' => null, // No ackcode in test mode
                                        'ackmsg' => 'TEST_MODE_PROCESSED' // Mark as test mode
                                    ]);
                                    Log::info('Process All Missing Z-Reports: Saved test mode record', [
                                        'date' => $date,
                                        'znumber' => $znumber
                                    ]);
                                }
                            } catch (\Exception $saveEx) {
                                Log::warning('Process All Missing Z-Reports: Failed to save test mode record', [
                                    'date' => $date,
                                    'error' => $saveEx->getMessage()
                                ]);
                                // Continue even if save fails
                            }
                        }

                        $processedDates[] = [
                            'date' => $date,
                            'znumber' => $znumber,
                            'status' => 'success',
                            'data' => $result['data']
                        ];
                        $successCount++;
                    } else {
                        $processedDates[] = [
                            'date' => $date,
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'error' => $result['error']
                        ];
                        $failureCount++;
                    }

                } catch (\Exception $ex) {
                    Log::error('Process All Missing Z-Reports: Failed to process date', [
                        'date' => $date,
                        'error' => $ex->getMessage()
                    ]);

                    $processedDates[] = [
                        'date' => $date,
                        'znumber' => $znumber,
                        'status' => 'failed',
                        'error' => $ex->getMessage()
                    ];
                    $failureCount++;
                }
            }

            $totalExecutionTime = round(microtime(true) - $startTime, 3);

            // Log batch completion summary
            Log::info('Process All Missing Z-Reports: Batch processing completed', [
                'batch_summary' => [
                    'total_missing' => $totalMissing,
                    'processed_in_batch' => $batchSize,
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'current_offset' => $offset,
                    'next_offset' => $offset + $batchSize,
                    'remaining' => max(0, $totalMissing - ($offset + $batchSize)),
                    'total_execution_time_seconds' => $totalExecutionTime,
                    'test_mode' => $testMode
                ],
                'successful_dates' => array_map(function($item) {
                    return [
                        'date' => $item['date'],
                        'znumber' => $item['znumber'],
                        'status' => $item['status']
                    ];
                }, array_filter($processedDates, function($item) {
                    return $item['status'] === 'success';
                }))
            ]);

            // Calculate next offset and last processed date for continuation
            $nextOffset = $offset + $batchSize;
            $lastProcessedDate = null;
            if (!empty($processedDates)) {
                $lastProcessedDate = end($processedDates)['date'];
            }
            
            // Check if there are more dates to process
            $hasMore = ($offset + $batchSize) < $totalMissing;
            
            // Update job status
            if ($job) {
                $progress = $totalMissing > 0 ? (($totalMissing - ($totalMissing - $nextOffset)) / $totalMissing) * 100 : 0;
                $currentDate = !empty($processedDates) ? end($processedDates)['date'] : null;
                
                $job->total_processed = $job->total_processed + $successCount + $failureCount;
                $job->total_successful = $job->total_successful + $successCount;
                $job->total_failed = $job->total_failed + $failureCount;
                $job->remaining = max(0, $totalMissing - $nextOffset);
                $job->current_offset = $nextOffset;
                $job->progress_percentage = $progress;
                $job->current_date_processing = $currentDate;
                $job->last_response = [
                    'summary' => [
                        'total_missing' => $totalMissing,
                        'processed_in_this_batch' => $batchSize,
                        'successful' => $successCount,
                        'failed' => $failureCount,
                        'remaining' => max(0, $totalMissing - $nextOffset)
                    ]
                ];
                
                if (!$hasMore) {
                    $job->status = ZreportProcessingJob::STATUS_COMPLETED;
                    $job->completed_at = now();
                    $job->progress_percentage = 100;
                    $job->remaining = 0;
                }
                
                $job->save();
            }

            return response()->json([
                'status' => 1,
                'message' => "Processed {$batchSize} of {$totalMissing} missing Z-reports. {$successCount} successful, {$failureCount} failed.",
                'test_mode' => $testMode,
                'data' => [
                    'date_range' => [
                        'start' => $startDateStr,
                        'end' => $endDateStr
                    ],
                    'summary' => [
                        'total_missing' => $totalMissing,
                        'processed_in_this_batch' => $batchSize,
                        'successful' => $successCount,
                        'failed' => $failureCount,
                        'remaining' => max(0, $totalMissing - $nextOffset),
                        'total_execution_time_seconds' => $totalExecutionTime,
                        'current_offset' => $offset,
                        'next_offset' => $nextOffset
                    ],
                    'processed_dates' => $processedDates,
                    'continuation' => $hasMore ? [
                        'has_more' => true,
                        'message' => 'More dates to process. Use the continuation parameters below to process the next batch.',
                        'next_offset' => $nextOffset,
                        'last_processed_date' => $lastProcessedDate,
                        'next_request_example' => [
                            'start_date' => $startDateStr,
                            'end_date' => $endDateStr,
                            'batch_size' => $batchSize,
                            'test_mode' => $testMode,
                            'offset' => $nextOffset,
                            'last_processed_date' => $lastProcessedDate
                        ]
                    ] : [
                        'has_more' => false,
                        'message' => 'All missing Z-reports have been processed!'
                    ]
                ]
            ]);

        } catch (\Exception $ex) {
            Log::error('Process All Missing Z-Reports: Failed', [
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString()
            ]);

            // Update job status if job_id is provided and connection error occurred
            $jobId = $request->input('job_id');
            if ($jobId && strpos($ex->getMessage(), 'Optimized database connection failed') !== false) {
                try {
                    $job = ZreportProcessingJob::where('job_id', $jobId)->first();
                    if ($job) {
                        $job->status = ZreportProcessingJob::STATUS_FAILED;
                        $job->error_message = $ex->getMessage();
                        $job->completed_at = now();
                        $job->save();
                    }
                } catch (\Exception $jobEx) {
                    Log::error('Process All Missing Z-Reports: Failed to update job status', [
                        'error' => $jobEx->getMessage()
                    ]);
                }
            }

            return response()->json([
                'status' => 0,
                'message' => 'Failed to process missing Z-reports',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Helper method to process a single date
     * Extracted from repostMissingZreports for reuse
     */
    private function processSingleDate($date, $testMode = true)
    {
        try {
            // Get VFD registration data
            $vfdData = DB::table('vfd_registration')
                ->select([
                    'name', 'city', 'mobile', 'address', 'username', 'uin', 'vrn',
                    'password', 'receiptcode', 'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                ])
                ->orderBy('id', 'desc')
                ->first();

            if (!$vfdData) {
                return ['success' => false, 'error' => 'VFD registration data not found'];
            }

            $vfdDataArray = (array)$vfdData;

            // Always use optimized database connection - no fallback
            // Connection failure will throw exception for caller to handle
            try {
                $optimizedDb = DB::connection('mysql_optimized');
                $optimizedDb->select('SELECT 1');
                Log::info('Process Single Date: Using optimized database connection', [
                    'host' => config('database.connections.mysql_optimized.host'),
                    'database' => config('database.connections.mysql_optimized.database')
                ]);
            } catch (\Exception $connEx) {
                $errorMsg = 'Optimized database connection failed. Host: ' . config('database.connections.mysql_optimized.host') . 
                           ', Database: ' . config('database.connections.mysql_optimized.database') . 
                           '. Error: ' . $connEx->getMessage();
                
                Log::error('Process Single Date: Optimized connection failed', [
                    'error' => $connEx->getMessage(),
                    'host' => config('database.connections.mysql_optimized.host'),
                    'database' => config('database.connections.mysql_optimized.database')
                ]);
                
                throw new \Exception($errorMsg);
            }

            // Get daily transaction data
            $dailyData = $optimizedDb->table('transactions')
                ->selectRaw("
                    COUNT(gc) as total_trans,
                    SUM(charged_amount) as daily_total,
                    SUM(CASE WHEN payment_type = 'CASH' THEN charged_amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_type = 'EMONEY' THEN charged_amount ELSE 0 END) as emoney_total,
                    SUM(ROUND(charged_amount / 1.18, 2)) as net_amount,
                    SUM(charged_amount - ROUND(charged_amount / 1.18, 2)) as tax_amount
                ")
                ->whereRaw("DATE(created_at) = ?", [$date])
                ->first();

            if (!$dailyData || ($dailyData->total_trans ?? 0) == 0) {
                return ['success' => false, 'error' => 'No transactions found for this date'];
            }

            $sumSalesPrevDay = $dailyData->daily_total ?? 0;
            $cashTotal = $dailyData->cash_total ?? 0.00;
            $emoneyTotal = $dailyData->emoney_total ?? 0.00;
            $totalNoTrans = $dailyData->total_trans ?? 0;
            $nettamount = $dailyData->net_amount ?? 0;
            $taxamount = $dailyData->tax_amount ?? 0;

            // Always calculate cumulative total from all transactions (Method 2)
            // This ensures accuracy by always calculating from source data
            Log::info('Process Single Date: Calculating cumulative from all transactions', [
                'date' => $date,
                'note' => 'Always calculating from transactions for accuracy. This may take several minutes for large datasets.'
            ]);
            
            try {
                // Set query timeout (5 minutes = 300000 milliseconds)
                // This gives enough time for large cumulative calculations
                $optimizedDb->statement("SET SESSION max_execution_time = 300000"); // 5 minutes
                $optimizedDb->statement("SET SESSION innodb_lock_wait_timeout = 300"); // 5 minutes for InnoDB locks
                
                $queryStartTime = microtime(true);
                
                // Calculate cumulative from all transactions
                // Using direct SQL for better performance with indexes
                $cumulativeResult = $optimizedDb->selectOne(
                    "SELECT SUM(charged_amount) as cumulative_total 
                     FROM transactions 
                     WHERE created_at <= ?",
                    [$date . ' 23:59:59']
                );
                
                $queryTime = round(microtime(true) - $queryStartTime, 2);
                $cumulativeTotal = $cumulativeResult->cumulative_total ?? 0;
                
                Log::info('Process Single Date: Cumulative calculated from transactions', [
                    'date' => $date,
                    'cumulative_total' => $cumulativeTotal,
                    'query_time_seconds' => $queryTime,
                    'calculation_method' => 'sum_all_transactions',
                    'note' => 'Calculated from all transactions from beginning'
                ]);
                
                // Validate result
                if ($cumulativeTotal === null) {
                    return ['success' => false, 'error' => 'Cumulative calculation returned null - possible timeout or no transactions found'];
                }
                
                // Warn if cumulative is 0 but daily total exists
                if ($cumulativeTotal == 0 && $sumSalesPrevDay > 0) {
                    Log::warning('Process Single Date: Cumulative is 0 but daily total exists', [
                        'date' => $date,
                        'daily_total' => $sumSalesPrevDay,
                        'note' => 'This might be the first Z-report'
                    ]);
                }
                    
            } catch (\Exception $queryEx) {
                $errorMsg = $queryEx->getMessage();
                $isTimeout = strpos($errorMsg, 'maximum statement execution time exceeded') !== false || 
                            strpos($errorMsg, 'Query execution was interrupted') !== false;
                
                if ($isTimeout) {
                    Log::error('Process Single Date: Cumulative calculation timed out', [
                        'date' => $date,
                        'error' => $errorMsg,
                        'suggestion' => 'Query exceeded 5 minute timeout. Consider optimizing database indexes on created_at column.'
                    ]);
                    
                    $errorMsg = 'Cumulative calculation timed out. The query exceeded the 5 minute execution limit. ';
                    $errorMsg .= 'Suggestions: 1) Ensure proper indexes on transactions.created_at column, ';
                    $errorMsg .= '2) Process dates chronologically from earliest to latest, ';
                    $errorMsg .= '3) Consider increasing database server resources or query timeout.';
                } else {
                    Log::error('Process Single Date: Cumulative calculation failed', [
                        'date' => $date,
                        'error' => $errorMsg
                    ]);
                }
                
                return [
                    'success' => false, 
                    'error' => 'Cumulative calculation failed: ' . $errorMsg
                ];
            }

            $znumber = date('Ymd', strtotime($date));
            $time = '23:59:59';

            // Post Z-Report if not in test mode
            $resp = null;
            if (!$testMode) {
                Log::info('Z-Report Repost: Posting to TRA API', [
                    'date' => $date,
                    'znumber' => $znumber,
                    'transaction_count' => $totalNoTrans,
                    'daily_total' => $sumSalesPrevDay
                ]);

                $resp = ZReportService::postZReport(
                    $vfdDataArray,
                    $date,
                    $time,
                    $znumber,
                    $sumSalesPrevDay,
                    $cumulativeTotal,
                    $totalNoTrans,
                    $nettamount,
                    $taxamount,
                    $emoneyTotal,
                    $cashTotal
                );

                // Log successful post with all data and save to database
                if (isset($resp['status']) && $resp['status'] == 1) {
                    // Save to database
                    try {
                        ZreportRepostLog::create([
                            'report_date' => $date,
                            'znumber' => $znumber,
                            'report_time' => $time,
                            'vfd_name' => $vfdDataArray['name'] ?? null,
                            'tin' => $vfdDataArray['tin'] ?? null,
                            'vrn' => $vfdDataArray['vrn'] ?? null,
                            'reg_id' => $vfdDataArray['reg_id'] ?? null,
                            'daily_total' => $sumSalesPrevDay,
                            'cumulative_total' => $cumulativeTotal,
                            'net_amount' => $nettamount,
                            'tax_amount' => $taxamount,
                            'cash_total' => $cashTotal,
                            'emoney_total' => $emoneyTotal,
                            'transaction_count' => $totalNoTrans,
                            'tra_status' => $resp['status'] ?? null,
                            'tra_ackcode' => $resp['data']['ackcode'] ?? null,
                            'tra_ackmsg' => $resp['data']['ackmsg'] ?? null,
                            'tra_received_date' => $resp['data']['received_date'] ?? null,
                            'tra_received_time' => $resp['data']['received_time'] ?? null,
                            'test_mode' => false,
                            'tra_response_data' => json_encode($resp),
                            'processing_status' => 'success',
                            'posted_at' => now()
                        ]);
                        Log::info('Z-Report Repost: Saved to database', [
                            'date' => $date,
                            'znumber' => $znumber
                        ]);
                    } catch (\Exception $saveEx) {
                        Log::error('Z-Report Repost: Failed to save to database', [
                            'date' => $date,
                            'znumber' => $znumber,
                            'error' => $saveEx->getMessage()
                        ]);
                    }

                    Log::info('Z-Report Repost: Successfully posted to TRA', [
                        'date' => $date,
                        'znumber' => $znumber,
                        'time' => $time,
                        'vfd_data' => [
                            'name' => $vfdDataArray['name'] ?? null,
                            'tin' => $vfdDataArray['tin'] ?? null,
                            'vrn' => $vfdDataArray['vrn'] ?? null,
                            'reg_id' => $vfdDataArray['reg_id'] ?? null
                        ],
                        'financial_data' => [
                            'daily_total' => $sumSalesPrevDay,
                            'cumulative_total' => $cumulativeTotal,
                            'net_amount' => $nettamount,
                            'tax_amount' => $taxamount,
                            'cash_total' => $cashTotal,
                            'emoney_total' => $emoneyTotal
                        ],
                        'transaction_data' => [
                            'total_transactions' => $totalNoTrans,
                            'transaction_date' => $date
                        ],
                        'tra_response' => [
                            'status' => $resp['status'] ?? null,
                            'message' => $resp['message'] ?? null,
                            'ackcode' => $resp['data']['ackcode'] ?? null,
                            'ackmsg' => $resp['data']['ackmsg'] ?? null,
                            'received_date' => $resp['data']['received_date'] ?? null,
                            'received_time' => $resp['data']['received_time'] ?? null
                        ],
                        'posted_at' => now()->toDateTimeString()
                    ]);
                } else {
                    // Save failed attempt to database
                    try {
                        ZreportRepostLog::create([
                            'report_date' => $date,
                            'znumber' => $znumber,
                            'report_time' => $time,
                            'vfd_name' => $vfdDataArray['name'] ?? null,
                            'tin' => $vfdDataArray['tin'] ?? null,
                            'vrn' => $vfdDataArray['vrn'] ?? null,
                            'reg_id' => $vfdDataArray['reg_id'] ?? null,
                            'daily_total' => $sumSalesPrevDay,
                            'cumulative_total' => $cumulativeTotal,
                            'net_amount' => $nettamount,
                            'tax_amount' => $taxamount,
                            'cash_total' => $cashTotal,
                            'emoney_total' => $emoneyTotal,
                            'transaction_count' => $totalNoTrans,
                            'tra_status' => $resp['status'] ?? null,
                            'test_mode' => false,
                            'tra_response_data' => json_encode($resp),
                            'error_message' => $resp['message'] ?? 'Unknown error',
                            'processing_status' => 'failed',
                            'posted_at' => now()
                        ]);
                    } catch (\Exception $saveEx) {
                        Log::error('Z-Report Repost: Failed to save failed attempt to database', [
                            'date' => $date,
                            'error' => $saveEx->getMessage()
                        ]);
                    }

                    Log::warning('Z-Report Repost: Post to TRA failed or returned error', [
                        'date' => $date,
                        'znumber' => $znumber,
                        'response' => $resp
                    ]);
                }
            } else {
                // Save test mode data to database
                try {
                    ZreportRepostLog::create([
                        'report_date' => $date,
                        'znumber' => $znumber,
                        'report_time' => $time,
                        'vfd_name' => $vfdDataArray['name'] ?? null,
                        'tin' => $vfdDataArray['tin'] ?? null,
                        'vrn' => $vfdDataArray['vrn'] ?? null,
                        'reg_id' => $vfdDataArray['reg_id'] ?? null,
                        'daily_total' => $sumSalesPrevDay,
                        'cumulative_total' => $cumulativeTotal,
                        'net_amount' => $nettamount,
                        'tax_amount' => $taxamount,
                        'cash_total' => $cashTotal,
                        'emoney_total' => $emoneyTotal,
                        'transaction_count' => $totalNoTrans,
                        'test_mode' => true,
                        'processing_status' => 'pending',
                        'error_message' => 'Test mode - not posted to TRA'
                    ]);
                } catch (\Exception $saveEx) {
                    Log::error('Z-Report Repost: Failed to save test mode data to database', [
                        'date' => $date,
                        'error' => $saveEx->getMessage()
                    ]);
                }

                // Log test mode data preparation
                Log::info('Z-Report Repost: Data prepared (test mode)', [
                    'date' => $date,
                    'znumber' => $znumber,
                    'time' => $time,
                    'vfd_data' => [
                        'name' => $vfdDataArray['name'] ?? null,
                        'tin' => $vfdDataArray['tin'] ?? null,
                        'vrn' => $vfdDataArray['vrn'] ?? null,
                        'reg_id' => $vfdDataArray['reg_id'] ?? null
                    ],
                    'financial_data' => [
                        'daily_total' => $sumSalesPrevDay,
                        'cumulative_total' => $cumulativeTotal,
                        'net_amount' => $nettamount,
                        'tax_amount' => $taxamount,
                        'cash_total' => $cashTotal,
                        'emoney_total' => $emoneyTotal
                    ],
                    'transaction_data' => [
                        'total_transactions' => $totalNoTrans,
                        'transaction_date' => $date
                    ],
                    'note' => 'Test mode - not posted to TRA'
                ]);
            }

            return [
                'success' => true,
                'data' => [
                    'date' => $date,
                    'znumber' => $znumber,
                    'transaction_count' => $totalNoTrans,
                    'daily_total' => $sumSalesPrevDay,
                    'cumulative_total' => $cumulativeTotal,
                    'net_amount' => $nettamount,
                    'tax_amount' => $taxamount,
                    'cash_total' => $cashTotal,
                    'emoney_total' => $emoneyTotal,
                    'response' => $resp,
                    'test_mode' => $testMode
                ]
            ];

        } catch (\Exception $ex) {
            return ['success' => false, 'error' => $ex->getMessage()];
        }
    }

    /**
     * Generate Z-Report payload for verification
     * Uses a previously successfully posted Z-report's znumber to regenerate the payload
     * This allows verification of calculations
     *
     * POST payload:
     * {
     *     "znumber": "20210401",        // Z-number from previously posted Z-report
     *     "include_token": false,        // Optional: include token in payload (default: false)
     *     "recalculate": false          // Optional: recalculate from transactions for verification (default: false)
     * }
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateZreportPayload(Request $request)
    {
        try {
            $znumber = $request->input('znumber');
            $includeToken = $request->input('include_token', false);
            $recalculate = $request->input('recalculate', false);

            if (empty($znumber)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Z-number is required',
                    'data' => []
                ], 400);
            }

            // Validate znumber format (should be YYYYMMDD)
            if (!preg_match('/^\d{8}$/', $znumber)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid znumber format. Expected YYYYMMDD format (e.g., 20210401)',
                    'data' => []
                ], 400);
            }

            // Extract date from znumber
            $date = substr($znumber, 0, 4) . '-' . substr($znumber, 4, 2) . '-' . substr($znumber, 6, 2);

            Log::info('Generate Z-Report Payload: Starting', [
                'znumber' => $znumber,
                'date' => $date
            ]);

            // Get the previously posted Z-report from vfd_zreport
            $vfdZreport = DB::table('vfd_zreport')
                ->where('znumber', $znumber)
                ->orderBy('id', 'desc')
                ->first();

            if (!$vfdZreport) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Z-report not found in vfd_zreport. Please provide a znumber from a successfully posted Z-report.',
                    'data' => []
                ], 404);
            }

            // Get the corresponding zreport record for full data
            $zreport = DB::table('zreport')
                ->where('znumber', $znumber)
                ->orderBy('id', 'desc')
                ->first();

            if (!$zreport) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Z-report not found in zreport table.',
                    'data' => []
                ], 404);
            }

            // Get current VFD registration data
            $vfdData = DB::table('vfd_registration')
                ->select([
                    'name', 'city', 'mobile', 'address', 'username', 'uin', 'vrn',
                    'password', 'receiptcode', 'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                ])
                ->orderBy('id', 'desc')
                ->first();

            if (!$vfdData) {
                return response()->json([
                    'status' => 0,
                    'message' => 'VFD registration data not found',
                    'data' => []
                ], 404);
            }

            $vfdDataArray = (array)$vfdData;

            // Recalculate from transactions if requested (for verification)
            $recalculatedData = null;
            if ($recalculate) {
                try {
                    // Always use optimized database connection - no fallback
                    // Connection failure will throw exception for caller to handle
                    try {
                        $optimizedDb = DB::connection('mysql_optimized');
                        $optimizedDb->select('SELECT 1');
                        Log::info('Generate Z-Report Payload: Using optimized database connection', [
                            'host' => config('database.connections.mysql_optimized.host'),
                            'database' => config('database.connections.mysql_optimized.database')
                        ]);
                    } catch (\Exception $connEx) {
                        $errorMsg = 'Optimized database connection failed. Host: ' . config('database.connections.mysql_optimized.host') . 
                                   ', Database: ' . config('database.connections.mysql_optimized.database') . 
                                   '. Error: ' . $connEx->getMessage();
                        
                        Log::error('Generate Z-Report Payload: Optimized connection failed', [
                            'error' => $connEx->getMessage(),
                            'host' => config('database.connections.mysql_optimized.host'),
                            'database' => config('database.connections.mysql_optimized.database')
                        ]);
                        
                        throw new \Exception($errorMsg);
                    }

                    // Get daily transaction data
                    $dailyData = $optimizedDb->table('transactions')
                        ->selectRaw("
                            COUNT(gc) as total_trans,
                            SUM(charged_amount) as daily_total,
                            SUM(CASE WHEN payment_type = 'CASH' THEN charged_amount ELSE 0 END) as cash_total,
                            SUM(CASE WHEN payment_type = 'EMONEY' THEN charged_amount ELSE 0 END) as emoney_total,
                            SUM(ROUND(charged_amount / 1.18, 2)) as net_amount,
                            SUM(charged_amount - ROUND(charged_amount / 1.18, 2)) as tax_amount
                        ")
                        ->whereRaw("DATE(created_at) = ?", [$date])
                        ->first();

                    // Always calculate cumulative total from all transactions (Method 2)
                    // This ensures accuracy by always calculating from source data
                    // Set query timeout (5 minutes = 300000 milliseconds)
                    $optimizedDb->statement("SET SESSION max_execution_time = 300000"); // 5 minutes
                    $optimizedDb->statement("SET SESSION innodb_lock_wait_timeout = 300"); // 5 minutes for InnoDB locks
                    
                    // Use direct SQL query for better performance with indexes
                    $cumulativeResult = $optimizedDb->selectOne(
                        "SELECT SUM(charged_amount) as cumulative_total 
                         FROM transactions 
                         WHERE created_at <= ?",
                        [$date . ' 23:59:59']
                    );
                    $cumulativeTotal = $cumulativeResult->cumulative_total ?? 0;

                    $recalculatedData = [
                        'daily_total' => $dailyData->daily_total ?? 0,
                        'cumulative_total' => $cumulativeTotal,
                        'transaction_count' => $dailyData->total_trans ?? 0,
                        'net_amount' => $dailyData->net_amount ?? 0,
                        'tax_amount' => $dailyData->tax_amount ?? 0,
                        'cash_total' => $dailyData->cash_total ?? 0,
                        'emoney_total' => $dailyData->emoney_total ?? 0
                    ];

                    Log::info('Generate Z-Report Payload: Recalculated from transactions', [
                        'znumber' => $znumber,
                        'recalculated_data' => $recalculatedData
                    ]);
                } catch (\Exception $recalcEx) {
                    Log::error('Generate Z-Report Payload: Recalculation failed', [
                        'znumber' => $znumber,
                        'error' => $recalcEx->getMessage()
                    ]);
                }
            }

            // Get token if requested
            $token = null;
            if ($includeToken) {
                $token = ZReportService::getToken();
                if (!$token) {
                    Log::warning('Generate Z-Report Payload: Failed to get token', [
                        'znumber' => $znumber
                    ]);
                }
            }

            // Build the payload that would be sent to TRA
            // Use recalculated data if available, otherwise use stored data
            $dailyTotal = $recalculatedData ? $recalculatedData['daily_total'] : ($zreport->dailytotalamount ?? 0);
            $gross = $recalculatedData ? $recalculatedData['cumulative_total'] : ($zreport->gross ?? 0);
            $ticketFiscal = $recalculatedData ? $recalculatedData['transaction_count'] : ($zreport->ticketfiscal ?? 0);
            $netAmount = $recalculatedData ? $recalculatedData['net_amount'] : ($zreport->netamount ?? 0);
            $taxAmount = $recalculatedData ? $recalculatedData['tax_amount'] : ($zreport->taxamount ?? 0);
            $cashAmount = $recalculatedData ? $recalculatedData['cash_total'] : ($zreport->cashamount ?? 0);
            $emoneyAmount = $recalculatedData ? $recalculatedData['emoney_total'] : ($zreport->emoney_amount ?? 0);
            $payload = [
                'DATE' => $zreport->date ? date('Y-m-d', strtotime($zreport->date)) : $date,
                'TIME' => $zreport->time ?? '23:59:59',
                'VRN' => $vfdDataArray['vrn'] ?? '',
                'TIN' => strval($vfdDataArray['tin'] ?? ''),
                'NAME' => $vfdDataArray['name'] ?? '',
                'CITY' => $vfdDataArray['city'] ?? '',
                'MOBILE' => $vfdDataArray['mobile'] ?? '',
                'ADDRESS' => $vfdDataArray['address'] ?? '',
                'TAXOFFICE' => $vfdDataArray['taxoffice'] ?? '',
                'REGID' => $vfdDataArray['reg_id'] ?? '',
                'ZNUMBER' => $znumber,
                'EFDSERIAL' => $vfdDataArray['serila'] ?? '',
                'REGISTRATIONDATE' => $vfdDataArray['date'] ?? '',
                'USER' => $vfdDataArray['uin'] ?? '',
                'SIMIMSI' => 'WEBAPI',
                'DAILYTOTALAMOUNT' => $dailyTotal,
                'GROSS' => $gross,
                'CORRECTIONS' => $zreport->corrections ?? 0.00,
                'DISCOUNTS' => $zreport->discounts ?? 0.00,
                'SURCHARGES' => $zreport->surcharges ?? 0.00,
                'TICKETSVOID' => $zreport->ticketsvoid ?? 0,
                'TICKETSVOIDTOTAL' => $zreport->ticketsvoidtotal ?? 0.00,
                'TICKETSFISCAL' => $ticketFiscal,
                'TICKETSNONFISCAL' => $zreport->ticketsnonfiscal ?? 0,
                'VATRATE' => $zreport->vatrate ?? 'A-18.00',
                'NETAMOUNT' => $netAmount,
                'TAXAMOUNT' => $taxAmount,
                'CASH_AMOUNT' => $cashAmount,
                'EMONEY_AMOUNT' => $emoneyAmount,
                'EMONEY_TYPE' => $zreport->emoney_type ?? 'EMONEY',
                'CASH_TYPE' => $zreport->cash_type ?? 'CASH',
                'VATCHANGENUM' => $zreport->vatchangenum ?? 0,
                'HEADCHANGENUM' => $zreport->headchangenum ?? 0,
                'FWVERSION' => 3.0,
                'FWCHECKSUM' => 'WEBAPI'
            ];

            if ($token) {
                $payload['token'] = $token;
            }

            // Also get the original response from vfd_zreport for comparison
            $originalResponse = [
                'ackcode' => $vfdZreport->ackcode ?? null,
                'ackmsg' => $vfdZreport->ackmsg ?? null,
                'received_date' => $vfdZreport->received_date ?? null,
                'received_time' => $vfdZreport->received_time ?? null,
                'created_at' => $vfdZreport->created_at ?? null
            ];

            Log::info('Generate Z-Report Payload: Payload generated', [
                'znumber' => $znumber,
                'date' => $date,
                'include_token' => $includeToken
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Z-Report payload generated successfully',
                'data' => [
                    'znumber' => $znumber,
                    'date' => $date,
                    'original_zreport' => [
                        'date' => $zreport->date,
                        'time' => $zreport->time,
                        'znumber' => $zreport->znumber,
                        'daily_total' => $zreport->dailytotalamount,
                        'cumulative_total' => $zreport->gross,
                        'transaction_count' => $zreport->ticketfiscal,
                        'net_amount' => $zreport->netamount,
                        'tax_amount' => $zreport->taxamount,
                        'cash_total' => $zreport->cashamount,
                        'emoney_total' => $zreport->emoney_amount
                    ],
                    'recalculated_data' => $recalculatedData ? [
                        'daily_total' => $recalculatedData['daily_total'],
                        'cumulative_total' => $recalculatedData['cumulative_total'],
                        'transaction_count' => $recalculatedData['transaction_count'],
                        'net_amount' => $recalculatedData['net_amount'],
                        'tax_amount' => $recalculatedData['tax_amount'],
                        'cash_total' => $recalculatedData['cash_total'],
                        'emoney_total' => $recalculatedData['emoney_total'],
                        'comparison' => [
                            'daily_total_match' => abs(($zreport->dailytotalamount ?? 0) - $recalculatedData['daily_total']) < 0.01,
                            'cumulative_total_match' => abs(($zreport->gross ?? 0) - $recalculatedData['cumulative_total']) < 0.01,
                            'transaction_count_match' => ($zreport->ticketfiscal ?? 0) == $recalculatedData['transaction_count'],
                            'net_amount_match' => abs((float)($zreport->netamount ?? 0) - (float)$recalculatedData['net_amount']) < 0.01,
                            'tax_amount_match' => abs((float)($zreport->taxamount ?? 0) - (float)$recalculatedData['tax_amount']) < 0.01,
                            'cash_total_match' => abs(($zreport->cashamount ?? 0) - $recalculatedData['cash_total']) < 0.01,
                            'emoney_total_match' => abs(($zreport->emoney_amount ?? 0) - $recalculatedData['emoney_total']) < 0.01
                        ]
                    ] : null,
                    'original_tra_response' => $originalResponse,
                    'generated_payload' => $payload,
                    'vfd_api_url' => config('params.api_urls.vfd.default') ?: config('params.api_urls.vfd.production'),
                    'endpoint' => 'receipt/post-report',
                    'note' => $includeToken
                        ? 'Payload includes token and can be posted directly to TRA API'
                        : 'Payload does not include token. Set include_token=true to include token, or add token before posting.',
                    'verification_info' => [
                        'use_case' => 'Verify calculations by comparing this payload with the original posted data',
                        'comparison_fields' => [
                            'DAILYTOTALAMOUNT' => 'Should match daily_total from transactions',
                            'GROSS' => 'Should match cumulative total',
                            'NETAMOUNT' => 'Should match calculated net amount',
                            'TAXAMOUNT' => 'Should match calculated tax amount',
                            'TICKETSFISCAL' => 'Should match transaction count'
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $ex) {
            Log::error('Generate Z-Report Payload: Failed', [
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString()
            ]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to generate Z-Report payload',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Start automated processing of all missing Z-reports
     * Runs the artisan command in the background and returns a job ID for status tracking
     * 
     * POST payload:
     * {
     *     "start_date": "20210324",  // Optional: defaults to 2021-03-24
     *     "end_date": "20250930",     // Optional: defaults to 2025-09-30
     *     "batch_size": 1,             // Optional: process N dates per request (default: 1)
     *     "test_mode": true            // Optional: true = dry run (default), false = actually post to TRA
     *     "delay": 2                    // Optional: delay between requests in seconds (default: 2)
     * }
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startAutomatedProcessing(Request $request)
    {
        try {
            $startDate = $request->input('start_date', '20210324');
            $endDate = $request->input('end_date', '20250930');
            $batchSize = (int)$request->input('batch_size', 1);
            $testMode = $request->input('test_mode', true);
            $delay = (int)$request->input('delay', 2);
            
            if (!is_bool($testMode)) {
                $testMode = filter_var($testMode, FILTER_VALIDATE_BOOLEAN);
            }
            
            // Validate date format
            if (!preg_match('/^\d{8}$/', $startDate) || !preg_match('/^\d{8}$/', $endDate)) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Invalid date format. Use YYYYMMDD format (e.g., 20210324)',
                    'data' => []
                ], 400);
            }
            
            // Check if there's already a running job
            $runningJob = ZreportProcessingJob::where('status', ZreportProcessingJob::STATUS_RUNNING)
                ->first();
            
            if ($runningJob) {
                return response()->json([
                    'status' => 0,
                    'message' => 'A processing job is already running',
                    'data' => [
                        'existing_job' => [
                            'job_id' => $runningJob->job_id,
                            'status' => $runningJob->status,
                            'started_at' => $runningJob->started_at,
                            'progress' => $runningJob->progress_percentage
                        ],
                        'note' => 'Use the status endpoint to check progress, or wait for it to complete'
                    ]
                ], 409); // Conflict status
            }
            
            // Generate unique job ID
            $jobId = 'zreport_' . Str::random(16) . '_' . time();
            
            // Create job record
            $job = ZreportProcessingJob::create([
                'job_id' => $jobId,
                'status' => ZreportProcessingJob::STATUS_PENDING,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'batch_size' => $batchSize,
                'test_mode' => $testMode,
                'started_at' => now()
            ]);
            
            Log::info('Start Automated Processing: Job created', [
                'job_id' => $jobId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'batch_size' => $batchSize,
                'test_mode' => $testMode
            ]);
            
            // Run the command in the background
            // Use exec to run in background (non-blocking)
            $artisanPath = base_path('artisan');
            $logFile = storage_path('logs/zreport-processing-' . $jobId . '.log');
            
            // Build command with job-id parameter
            $command = sprintf(
                'cd %s && nohup php %s zreport:process-all-missing --start-date=%s --end-date=%s --batch-size=%d --test-mode=%s --delay=%d --job-id=%s >> %s 2>&1 & echo $!',
                base_path(),
                $artisanPath,
                escapeshellarg($startDate),
                escapeshellarg($endDate),
                $batchSize,
                $testMode ? 'true' : 'false',
                $delay,
                escapeshellarg($jobId),
                escapeshellarg($logFile)
            );
            
            $processId = null;
            if (function_exists('exec')) {
                exec($command, $output);
                $processId = !empty($output) ? trim($output[0]) : null;
            } else {
                // Fallback: try using Artisan::call in background (but this might block)
                // For true background, exec is needed
                Log::warning('Start Automated Processing: exec() not available, command may block', [
                    'job_id' => $jobId
                ]);
            }
            
            // Update job status to running
            $job->status = ZreportProcessingJob::STATUS_RUNNING;
            $job->save();
            
            Log::info('Start Automated Processing: Command started', [
                'job_id' => $jobId,
                'process_id' => $processId,
                'command' => $command
            ]);
            
            return response()->json([
                'status' => 1,
                'message' => 'Automated processing started successfully',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'running',
                    'process_id' => $processId,
                    'configuration' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'batch_size' => $batchSize,
                        'test_mode' => $testMode,
                        'delay' => $delay
                    ],
                    'status_endpoint' => url('/api/z-report/processing-status/' . $jobId),
                    'note' => 'Use the status endpoint to check progress. The job will run in the background.'
                ]
            ]);
            
        } catch (\Exception $ex) {
            Log::error('Start Automated Processing: Failed', [
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Failed to start automated processing',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Get status of automated processing job
     * 
     * GET /api/z-report/processing-status/{job_id}
     * 
     * @param string $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProcessingStatus($jobId)
    {
        try {
            $job = ZreportProcessingJob::where('job_id', $jobId)->first();
            
            if (!$job) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Job not found',
                    'data' => []
                ], 404);
            }
            
            // Calculate estimated time remaining if running
            $estimatedRemaining = null;
            if ($job->status === ZreportProcessingJob::STATUS_RUNNING && $job->total_processed > 0) {
                $elapsed = $job->started_at ? now()->diffInSeconds($job->started_at) : 0;
                $avgTimePerBatch = $elapsed / max($job->total_processed, 1);
                $estimatedRemaining = (int)($avgTimePerBatch * $job->remaining);
                $job->estimated_seconds_remaining = $estimatedRemaining;
                $job->save();
            }
            
            return response()->json([
                'status' => 1,
                'message' => 'Job status retrieved successfully',
                'data' => [
                    'job_id' => $job->job_id,
                    'status' => $job->status,
                    'configuration' => [
                        'start_date' => $job->start_date,
                        'end_date' => $job->end_date,
                        'batch_size' => $job->batch_size,
                        'test_mode' => $job->test_mode
                    ],
                    'progress' => [
                        'total_missing' => $job->total_missing,
                        'total_processed' => $job->total_processed,
                        'total_successful' => $job->total_successful,
                        'total_failed' => $job->total_failed,
                        'remaining' => $job->remaining,
                        'current_offset' => $job->current_offset,
                        'progress_percentage' => $job->progress_percentage,
                        'current_date_processing' => $job->current_date_processing
                    ],
                    'timing' => [
                        'started_at' => $job->started_at,
                        'completed_at' => $job->completed_at,
                        'estimated_seconds_remaining' => $estimatedRemaining,
                        'estimated_completion_time' => $estimatedRemaining ? now()->addSeconds($estimatedRemaining) : null
                    ],
                    'error' => $job->error_message,
                    'last_response' => $job->last_response
                ]
            ]);
            
        } catch (\Exception $ex) {
            Log::error('Get Processing Status: Failed', [
                'job_id' => $jobId,
                'error' => $ex->getMessage()
            ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get processing status',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Get all processing jobs (for monitoring)
     * 
     * GET /api/z-report/processing-jobs
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllProcessingJobs(Request $request)
    {
        try {
            $status = $request->input('status'); // Optional filter
            $limit = (int)$request->input('limit', 50);
            
            $query = ZreportProcessingJob::query();
            
            if ($status) {
                $query->where('status', $status);
            }
            
            $jobs = $query->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
            
            return response()->json([
                'status' => 1,
                'message' => 'Processing jobs retrieved successfully',
                'data' => [
                    'jobs' => $jobs->map(function($job) {
                        return [
                            'job_id' => $job->job_id,
                            'status' => $job->status,
                            'start_date' => $job->start_date,
                            'end_date' => $job->end_date,
                            'test_mode' => $job->test_mode,
                            'total_missing' => $job->total_missing,
                            'total_processed' => $job->total_processed,
                            'total_successful' => $job->total_successful,
                            'total_failed' => $job->total_failed,
                            'progress_percentage' => $job->progress_percentage,
                            'started_at' => $job->started_at,
                            'completed_at' => $job->completed_at,
                            'status_url' => url('/api/z-report/processing-status/' . $job->job_id)
                        ];
                    }),
                    'summary' => [
                        'total' => $jobs->count(),
                        'running' => $jobs->where('status', ZreportProcessingJob::STATUS_RUNNING)->count(),
                        'completed' => $jobs->where('status', ZreportProcessingJob::STATUS_COMPLETED)->count(),
                        'failed' => $jobs->where('status', ZreportProcessingJob::STATUS_FAILED)->count(),
                        'pending' => $jobs->where('status', ZreportProcessingJob::STATUS_PENDING)->count()
                    ]
                ]
            ]);
            
        } catch (\Exception $ex) {
            Log::error('Get All Processing Jobs: Failed', [
                'error' => $ex->getMessage()
            ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Failed to get processing jobs',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Stop/Cancel a running processing job
     * 
     * POST /api/z-report/stop-processing/{jobId}
     * 
     * @param string $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopProcessing($jobId)
    {
        try {
            $job = ZreportProcessingJob::where('job_id', $jobId)->first();
            
            if (!$job) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Job not found',
                    'data' => []
                ], 404);
            }
            
            // Check if job can be stopped
            if ($job->status === ZreportProcessingJob::STATUS_COMPLETED) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Job is already completed and cannot be stopped',
                    'data' => [
                        'job_id' => $jobId,
                        'status' => $job->status,
                        'completed_at' => $job->completed_at
                    ]
                ], 400);
            }
            
            if ($job->status === ZreportProcessingJob::STATUS_CANCELLED) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Job is already cancelled',
                    'data' => [
                        'job_id' => $jobId,
                        'status' => $job->status
                    ]
                ], 400);
            }
            
            if ($job->status === ZreportProcessingJob::STATUS_FAILED) {
                return response()->json([
                    'status' => 0,
                    'message' => 'Job has already failed and cannot be stopped',
                    'data' => [
                        'job_id' => $jobId,
                        'status' => $job->status,
                        'error_message' => $job->error_message
                    ]
                ], 400);
            }
            
            // Update job status to cancelled
            $job->status = ZreportProcessingJob::STATUS_CANCELLED;
            $job->error_message = 'Cancelled by user via API';
            $job->completed_at = now();
            $job->save();
            
            Log::info('Stop Processing: Job cancelled', [
                'job_id' => $jobId,
                'previous_status' => $job->getOriginal('status'),
                'total_processed' => $job->total_processed,
                'total_successful' => $job->total_successful,
                'total_failed' => $job->total_failed,
                'remaining' => $job->remaining
            ]);
            
            // Note: Actually killing the background process is complex and depends on:
            // - Process ID tracking (we'd need to store PID when starting)
            // - System permissions
            // - Process might have already moved to next iteration
            // For now, we mark it as cancelled and the command will check status on next iteration
            
            return response()->json([
                'status' => 1,
                'message' => 'Processing job cancelled successfully',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'cancelled',
                    'cancelled_at' => $job->completed_at,
                    'progress_at_cancellation' => [
                        'total_processed' => $job->total_processed,
                        'total_successful' => $job->total_successful,
                        'total_failed' => $job->total_failed,
                        'remaining' => $job->remaining,
                        'progress_percentage' => $job->progress_percentage
                    ],
                    'note' => 'The background process will stop on its next iteration when it checks the job status. If you need immediate termination, you may need to manually kill the process.'
                ]
            ]);
            
        } catch (\Exception $ex) {
            Log::error('Stop Processing: Failed', [
                'job_id' => $jobId,
                'error' => $ex->getMessage()
            ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Failed to stop processing job',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }
    
    /**
     * Stop all running processing jobs
     * 
     * POST /api/z-report/stop-all-processing
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopAllProcessing()
    {
        try {
            $runningJobs = ZreportProcessingJob::whereIn('status', [
                ZreportProcessingJob::STATUS_RUNNING,
                ZreportProcessingJob::STATUS_PENDING
            ])->get();
            
            if ($runningJobs->isEmpty()) {
                return response()->json([
                    'status' => 1,
                    'message' => 'No running or pending jobs found',
                    'data' => [
                        'cancelled_count' => 0
                    ]
                ]);
            }
            
            $cancelledCount = 0;
            $cancelledJobIds = [];
            
            foreach ($runningJobs as $job) {
                $job->status = ZreportProcessingJob::STATUS_CANCELLED;
                $job->error_message = 'Cancelled by user via API (stop all)';
                $job->completed_at = now();
                $job->save();
                $cancelledCount++;
                $cancelledJobIds[] = $job->job_id;
            }
            
            Log::info('Stop All Processing: Cancelled all running jobs', [
                'cancelled_count' => $cancelledCount,
                'job_ids' => $cancelledJobIds
            ]);
            
            return response()->json([
                'status' => 1,
                'message' => "Successfully cancelled {$cancelledCount} job(s)",
                'data' => [
                    'cancelled_count' => $cancelledCount,
                    'cancelled_job_ids' => $cancelledJobIds
                ]
            ]);
            
        } catch (\Exception $ex) {
            Log::error('Stop All Processing: Failed', [
                'error' => $ex->getMessage()
            ]);
            
            return response()->json([
                'status' => 0,
                'message' => 'Failed to stop all processing jobs',
                'error' => $ex->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Days in [start_date, end_date] whose Ymd is not present in vfd_zreport.znumber (same logic as the legacy SQL).
     *
     * GET /z-report/missing-znumbers?start_date=20210324&end_date=20260316
     * Query: start_date, end_date — each optional; Ymd (8 digits) or Y-m-d. Defaults: 20210324, 20260316.
     */
    public function listMissingZnumbers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'start_date' => ['nullable', 'string'],
            'end_date' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $startYmd = $this->parseZreportRangeDate($request->query('start_date'), '20210324');
        $endYmd = $this->parseZreportRangeDate($request->query('end_date'), '20260316');

        if ($startYmd === null || $endYmd === null) {
            return response()->json([
                'status' => 0,
                'message' => 'start_date and end_date must be Ymd (e.g. 20210324) or Y-m-d.',
            ], 422);
        }

        if ($startYmd > $endYmd) {
            return response()->json([
                'status' => 0,
                'message' => 'start_date must be on or before end_date.',
            ], 422);
        }

        $startDt = \DateTime::createFromFormat('Ymd', $startYmd);
        $endDt = \DateTime::createFromFormat('Ymd', $endYmd);
        $daySpan = $startDt && $endDt ? ($startDt->diff($endDt)->days + 1) : 0;
        if ($daySpan > 10000) {
            return response()->json([
                'status' => 0,
                'message' => 'Date range too large (maximum 10000 days).',
            ], 422);
        }

        $sql = <<<'SQL'
SELECT
  DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') AS missing_znumber,
  DATE_ADD(d.start_date, INTERVAL n.n DAY) AS missing_date
FROM
  (
    SELECT
      STR_TO_DATE(?, '%Y%m%d') AS start_date,
      STR_TO_DATE(?, '%Y%m%d') AS end_date
  ) d
  JOIN (
    SELECT
      a.N + b.N * 10 + c.N * 100 + d.N * 1000 + e.N * 10000 AS n
    FROM
      (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
              SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
      (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
              SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b,
      (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
              SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c,
      (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
              SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) d,
      (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL
              SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) e
  ) n
WHERE
  DATE_ADD(d.start_date, INTERVAL n.n DAY) <= d.end_date
  AND DATE_FORMAT(DATE_ADD(d.start_date, INTERVAL n.n DAY), '%Y%m%d') NOT IN (
    SELECT znumber FROM vfd_zreport WHERE znumber IS NOT NULL
  )
ORDER BY missing_date
SQL;

        try {
            $rows = DB::select($sql, [$startYmd, $endYmd]);
        } catch (\Throwable $e) {
            Log::error('listMissingZnumbers: query failed', ['error' => $e->getMessage()]);

            return response()->json([
                'status' => 0,
                'message' => 'Failed to load missing z-numbers.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $list = array_map(static function ($row) {
            return [
                'missing_znumber' => (string) $row->missing_znumber,
                'missing_date' => $row->missing_date,
            ];
        }, $rows);

        return response()->json([
            'status' => 1,
            'message' => 'OK',
            'data' => [
                'start_date' => $startYmd,
                'end_date' => $endYmd,
                'count' => count($list),
                'missing' => $list,
            ],
        ]);
    }

    /**
     * @param  mixed  $value
     */
    private function parseZreportRangeDate($value, string $defaultYmd): ?string
    {
        if ($value === null || $value === '') {
            return $defaultYmd;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{8}$/', $value)) {
            $dt = \DateTime::createFromFormat('Ymd', $value);

            return ($dt && $dt->format('Ymd') === $value) ? $value : null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $value);

            return $dt ? $dt->format('Ymd') : null;
        }

        return null;
    }
}

