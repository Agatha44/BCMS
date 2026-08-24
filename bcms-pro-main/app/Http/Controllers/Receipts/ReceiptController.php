<?php

namespace App\Http\Controllers\Receipts;

use App\Http\Controllers\Controller;
use App\Models\Zreport;
use App\Models\VfdToken;
use App\Models\VfdZreport;
use App\Helpers\ZReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReceiptController extends Controller
{
    /**
     * Helper function to format date/time fields from database
     */
    private function formatDateTime($value, $default = null, $format = 'Y-m-d')
    {
        if (!$value) {
            return $default;
        }
        
        if (is_string($value)) {
            return $value;
        }
        
        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format($format);
        }
        
        return (string)$value ?: $default;
    }
    /**
     * Prepare Z-Report Data (Phase 1)
     * Calculates all Z-report data and saves to zreport table
     * This should be run at end of day (can be scheduled)
     * 
     * POST /api/z-report/prepare
     * Payload: { "znumber": "20210422" }
     */
    public function prepareZReport(Request $request)
    {
        set_time_limit(1800); // 30 minutes
        ini_set('max_execution_time', 1800);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Prepare: Starting', ['znumber' => $request->input('znumber')]);

            // Request data - only znumber required
            $znumber = $request->input('znumber');
            
            if (!$znumber) {
                return response()->json([
                    'success' => false,
                    'error' => 'znumber is required',
                    'suggestion' => 'Provide znumber in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            // Validate znumber format (should be YYYYMMDD)
            if (!preg_match('/^\d{8}$/', $znumber)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid znumber format',
                    'suggestion' => 'znumber must be in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            // Derive date, time, and zdate from znumber
            $year = substr($znumber, 0, 4);
            $month = substr($znumber, 4, 2);
            $day = substr($znumber, 6, 2);
            $date = "{$year}-{$month}-{$day}";
            $time = '23:59:59'; // End of day for Z-reports
            $zdate = "DATE(created_at) = '{$date}'"; // SQL condition for the date


            // Check if already prepared
            $existingZreport = Zreport::where('znumber', $znumber)->first();
            
            if ($existingZreport) {
                // Check if already posted successfully (status = "success")
                if (strtolower($existingZreport->ackmsg ?? '') === 'success') {
                    Log::info('Z-Report Prepare: Already posted successfully, skipping preparation', [
                        'znumber' => $znumber,
                        'ackmsg' => $existingZreport->ackmsg,
                        'ackcode' => $existingZreport->ackcode
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Z-report already posted successfully. No need to reprepare.',
                        'znumber' => $znumber,
                        'ackcode' => $existingZreport->ackcode,
                        'ackmsg' => $existingZreport->ackmsg,
                        'data' => $existingZreport
                    ]);
                }
                
                // If ackmsg is null or not "success" (including "prepared", "TEST_MODE_PROCESSED", etc.)
                // Reprepare the record
                if ($existingZreport->ackmsg === null || strtolower($existingZreport->ackmsg) !== 'success') {
                    Log::info('Z-Report Prepare: Found existing record that needs reprepare', [
                        'znumber' => $znumber,
                        'current_ackmsg' => $existingZreport->ackmsg,
                        'current_gross' => $existingZreport->gross,
                        'action' => 'Will reprepare and update with status "prepared"'
                    ]);
                    // Continue to reprepare - will update existing record
                }
            }

            // VFD registration data (using default connection)
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();

            if (!$vfdData) {
                Log::error('Z-Report Prepare: VFD registration data not found');
                return response()->json([
                    'success' => false,
                    'error' => 'VFD registration data not found. Please ensure vfd_registration table has data.',
                    'znumber' => $znumber
                ], 404);
            }
            

            // Get daily data
            $dailyData = DB::table('transactions')
                ->selectRaw("
                    COUNT(gc) as total_trans,
                    SUM(charged_amount) as daily_total,
                    SUM(CASE WHEN payment_type = 'CASH' THEN charged_amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_type = 'EMONEY' THEN charged_amount ELSE 0 END) as emoney_total,
                    SUM(ROUND(charged_amount / 1.18, 2)) as net_amount,
                    SUM(charged_amount - ROUND(charged_amount / 1.18, 2)) as tax_amount
                ")
                ->whereRaw($zdate)
                ->first();

            $dailySales = $dailyData->daily_total ?? 0;
            $cashTotal = $dailyData->cash_total ?? 0;
            $emoneyTotal = $dailyData->emoney_total ?? 0;
            $totalTransactions = $dailyData->total_trans ?? 0;
            $netAmount = $dailyData->net_amount ?? 0;
            $taxAmount = $dailyData->tax_amount ?? 0;

            // Smart Cumulative Calculation - try to use previous zreport for fast calculation
            $previousZreport = Zreport::where('date', '<', $date)
                ->whereNotNull('gross')
                ->orderBy('date', 'desc')
                ->first();

            $cumulativeTotal = 0;
            $calculationMethod = '';

            if ($previousZreport) {
                // Use previous gross + current daily total (FAST)
                $previousGross = $previousZreport->gross ?? 0;
                $cumulativeTotal = $previousGross + $dailySales;
                $calculationMethod = 'previous_gross_plus_daily';
                
                Log::info('Z-Report Prepare: Using previous zreport for cumulative (fast)', [
                    'previous_znumber' => $previousZreport->znumber,
                    'previous_gross' => $previousGross,
                    'calculated_cumulative' => $cumulativeTotal
                ]);
            } else {
                // No previous zreport found, calculate from start (SLOW)
                Log::warning('Z-Report Prepare: No previous zreport found, calculating from start (this may take time)');
                
                DB::statement("SET SESSION max_execution_time = 1200000"); // 20 minutes
                DB::statement("SET SESSION innodb_lock_wait_timeout = 1200");
                
                $cumulativeTotal = DB::table('transactions')
                    ->whereRaw("DATE(created_at) <= ?", [$date])
                    ->sum('charged_amount');

                $calculationMethod = 'calculated_from_start';
            }

            if ($cumulativeTotal === null) {
                $cumulativeTotal = 0;
            }

            // Save to zreport table (mark as ready for posting)
            $zreportData = [
                'date' => $date,
                'time' => $time,
                'vrn' => $vfdData->vrn ?? null,
                'tin' => $vfdData->tin ?? null,
                'name' => $vfdData->name ?? null,
                'taxoffice' => $vfdData->taxoffice ?? null,
                'regid' => $vfdData->reg_id ?? null,
                'znumber' => $znumber,
                'efdserial' => $vfdData->serila ?? null,
                'registrationdate' => $vfdData->date ?? null,
                'user' => $vfdData->uin ?? null,
                'simimsi' => 'WEBAPI',
                'dailytotalamount' => $dailySales,
                'gross' => $cumulativeTotal,
                'corrections' => 0.00,
                'discounts' => 0.00,
                'surcharges' => 0.00,
                'ticketsvoid' => 0,
                'ticketsvoidtotal' => 0,
                'ticketfiscal' => $totalTransactions,
                'ticketsnonfiscal' => 0,
                'vatrate' => 'A-18.00',
                'netamount' => $netAmount,
                'taxamount' => $taxAmount,
                'cashamount' => $cashTotal,
                'emoney_amount' => $emoneyTotal,
                'emoney_type' => 'EMONEY',
                'cash_type' => 'CASH',
                'vatchangenum' => 0,
                'headchangenum' => 0,
                'city' => $vfdData->city ?? null,
                'mobile' => $vfdData->mobile ?? null,
                'address' => $vfdData->address ?? null,
                'ackcode' => null,
                'ackmsg' => 'prepared' // Status: prepared and ready for posting
            ];

            // Update existing or insert new
            if ($existingZreport) {
                $existingZreport->update($zreportData);
                $zreport = $existingZreport->fresh();
            } else {
                $zreport = Zreport::create($zreportData);
            }
            
            Log::info('Z-Report Prepare: Data saved', [
                'znumber' => $znumber,
                'calculation_method' => $calculationMethod,
                'cumulative_total' => $cumulativeTotal
            ]);
            
            $wasReprepared = $existingZreport && (
                $existingZreport->ackmsg === null || 
                strtolower($existingZreport->ackmsg) !== 'success'
            );
            
            Log::info('Z-Report Prepare: Data saved/updated, marked as prepared', [
                'znumber' => $znumber,
                'status' => 'prepared',
                'was_reprepared' => $wasReprepared,
                'previous_ackmsg' => $existingZreport->ackmsg ?? null,
                'is_new' => !$existingZreport
            ]);

            $totalTime = round(microtime(true) - $startTime, 2);

            return response()->json([
                'success' => true,
                'message' => 'Z-report data prepared and saved',
                'znumber' => $znumber,
                'calculation_method' => $calculationMethod,
                'data' => $zreport,
                'preparation_time_seconds' => $totalTime
            ]);

        } catch (\Exception $e) {
            Log::error('Z-Report Prepare: Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Post Z-Report to TRA (Phase 2)
     * Reads pre-calculated data from zreport table and posts to TRA
     * 
     * POST /api/z-report/batch
     */
    public function batchReport(Request $request)
    {
        set_time_limit(300); // 5 minutes (should be fast since data is pre-calculated)
        ini_set('max_execution_time', 300);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Batch: Starting', ['znumber' => $request->input('znumber')]);

            // Request data - only znumber required
            $znumber = $request->input('znumber');
            if (!$znumber) {
                return response()->json([
                    'success' => false,
                    'error' => 'znumber is required',
                    'suggestion' => 'Provide znumber in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            // Validate znumber format (should be YYYYMMDD)
            if (!preg_match('/^\d{8}$/', $znumber)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid znumber format',
                    'suggestion' => 'znumber must be in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }


            // Step 1: Read pre-calculated data from zreport table (using default connection)
            Log::info('Z-Report Batch: Step 1 - Reading pre-calculated data from zreport table');
            $zreport = Zreport::where('znumber', $znumber)->first();

            if (!$zreport) {
                Log::warning('Z-Report Batch: Step 1 - Z-report data not found', [
                    'znumber' => $znumber,
                    'suggestion' => 'Run prepare endpoint first'
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Z-report data not found for znumber: ' . $znumber,
                    'suggestion' => 'Please run POST /api/z-report/prepare first to calculate and save Z-report data',
                    'required_action' => 'prepare'
                ], 404);
            }

            // Step 1.1: Check if status is "prepared" (ready for posting) or a token error (retryable)
            $currentStatus = strtolower($zreport->ackmsg ?? '');
            $ackmsgOriginal = $zreport->ackmsg ?? null;
            
            // Check if status indicates a token error (retryable)
            $isTokenError = function($status) {
                if (empty($status)) return false;
                $statusLower = strtolower($status);
                return (
                    stripos($statusLower, 'invalid token') !== false ||
                    stripos($statusLower, 'token expired') !== false ||
                    stripos($statusLower, 'bearer') !== false && stripos($statusLower, 'invalid') !== false
                );
            };
            
            if ($currentStatus !== 'prepared' && !$isTokenError($ackmsgOriginal)) {

                if ($currentStatus === 'success') {
                    return response()->json([
                        'success' => false,
                        'error' => 'Z-report already posted successfully',
                        'znumber' => $znumber,
                        'current_status' => $zreport->ackmsg,
                        'ackcode' => $zreport->ackcode,
                        'suggestion' => 'This Z-report has already been posted. No need to post again.'
                    ], 400);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Z-report is not ready for posting',
                        'znumber' => $znumber,
                        'current_status' => $zreport->ackmsg ?? 'null',
                        'expected_status' => 'prepared',
                        'suggestion' => 'Please run POST /api/z-report/prepare first to prepare the data',
                        'required_action' => 'prepare'
                    ], 400);
                }
            }
            
            // If it's a token error, refresh token and reset status to allow posting
            if ($isTokenError($ackmsgOriginal)) {
                Log::info('Z-Report Batch: Token error detected, refreshing token', ['znumber' => $znumber]);
                
                $tokenData = ZReportService::requestToken();
                if (!$tokenData) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Failed to refresh token. Please try again.',
                        'znumber' => $znumber
                    ], 500);
                }
                
                // Update token in database
                $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
                if ($vfdToken) {
                    $vfdToken->token = $tokenData['access_token'] ?? null;
                    $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                    $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                    $vfdToken->created_at = date('Y-m-d H:i:s');
                    $vfdToken->created_by = 1;
                    $vfdToken->save();
                }
                
                // Reset status to allow posting
                Zreport::where('znumber', $znumber)->update([
                    'ackmsg' => 'prepared',
                    'ackcode' => null
                ]);
            }

            // Validate that required fields are present
            $missingFields = [];
            if ($zreport->gross === null) {
                $missingFields[] = 'gross (cumulative_total)';
            }
            if ($zreport->dailytotalamount === null) {
                $missingFields[] = 'dailytotalamount';
            }
            if ($zreport->netamount === null) {
                $missingFields[] = 'netamount';
            }
            if ($zreport->taxamount === null) {
                $missingFields[] = 'taxamount';
            }

            if (!empty($missingFields)) {
                Log::warning('Z-Report Batch: Step 1.2 - Missing required fields', [
                    'znumber' => $znumber,
                    'missing_fields' => $missingFields
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Z-report data is incomplete',
                    'missing_fields' => $missingFields,
                    'suggestion' => 'Please run POST /api/z-report/prepare again to recalculate missing data',
                    'required_action' => 'reprepare'
                ], 400);
            }

            // Get VFD registration data
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();
            
            if (!$vfdData) {
                Log::error('Z-Report Batch: Step 2 - VFD registration data not found');
                return response()->json([
                    'success' => false,
                    'error' => 'VFD registration data not found. Please ensure vfd_registration table has data.',
                    'znumber' => $znumber
                ], 404);
            }
            

            // Prepare data for posting
            $date = $zreport->date;
            if ($date) {
                if (is_string($date)) {
                    // Already a string, use as is (format: Y-m-d)
                    $date = $date;
                } elseif (is_object($date) && method_exists($date, 'format')) {
                    // Carbon object, format it
                    $date = $date->format('Y-m-d');
                } else {
                    // Other type, convert to string
                    $date = (string)$date;
                }
            } else {
                $date = null;
            }
            
            // Handle time format
            $time = $zreport->time;
            if ($time) {
                if (is_string($time)) {
                    // Already a string, use as is
                    $time = $time;
                } elseif (is_object($time) && method_exists($time, 'format')) {
                    // Carbon object, format it
                    $time = $time->format('H:i:s');
                } else {
                    // Other type, convert to string or default
                    $time = (string)$time ?: '23:59:59';
                }
            } else {
                $time = '23:59:59';
            }
            
            // Handle registrationdate format
            $registrationDate = $zreport->registrationdate ?? null;
            if ($registrationDate) {
                if (is_string($registrationDate)) {
                    // Already a string, use as is
                    $registrationDate = $registrationDate;
                } elseif (is_object($registrationDate) && method_exists($registrationDate, 'format')) {
                    // Carbon object, format it
                    $registrationDate = $registrationDate->format('Y-m-d');
                } else {
                    // Other type, convert to string
                    $registrationDate = (string)$registrationDate;
                }
            } else {
                $registrationDate = null;
            }
            
            $dailySales = $zreport->dailytotalamount ?? 0;
            $cumulativeTotal = $zreport->gross ?? 0;
            $totalTransactions = $zreport->ticketfiscal ?? 0;
            $netAmount = $zreport->netamount ?? 0;
            $taxAmount = $zreport->taxamount ?? 0;
            $cashTotal = $zreport->cashamount ?? 0;
            $emoneyTotal = $zreport->emoney_amount ?? 0;


            // Build response data from zreport
            $zreportData = [
                'date' => $date,
                'time' => $time,
                'vrn' => $zreport->vrn ?? null,
                'tin' => $zreport->tin ?? null,
                'name' => $zreport->name ?? null,
                'taxoffice' => $zreport->taxoffice ?? null,
                'regid' => $zreport->regid ?? null,
                'znumber' => $znumber,
                'efdserial' => $zreport->efdserial ?? null,
                'registrationdate' => $this->formatDateTime($zreport->registrationdate ?? null, null, 'Y-m-d'),
                'user' => $zreport->user ?? null,
                'simimsi' => $zreport->simimsi ?? 'WEBAPI',
                'dailytotalamount' => $dailySales,
                'gross' => $cumulativeTotal,
                'corrections' => $zreport->corrections ?? 0.00,
                'discounts' => $zreport->discounts ?? 0.00,
                'surcharges' => $zreport->surcharges ?? 0.00,
                'ticketsvoid' => $zreport->ticketsvoid ?? 0,
                'ticketsvoidtotal' => $zreport->ticketsvoidtotal ?? 0,
                'ticketfiscal' => $totalTransactions,
                'ticketsnonfiscal' => $zreport->ticketsnonfiscal ?? 0,
                'vatrate' => $zreport->vatrate ?? 'A-18.00',
                'netamount' => $netAmount,
                'taxamount' => $taxAmount,
                'cashamount' => $cashTotal,
                'emoney_amount' => $emoneyTotal,
                'emoney_type' => $zreport->emoney_type ?? 'EMONEY',
                'cash_type' => $zreport->cash_type ?? 'CASH',
                'vatchangenum' => $zreport->vatchangenum ?? 0,
                'headchangenum' => $zreport->headchangenum ?? 0,
                'city' => $zreport->city ?? null,
                'mobile' => $zreport->mobile ?? null,
                'address' => $zreport->address ?? null,
            ];


            // Post Z-Report to TRA (matching Yii2 flow)
            // Pass $vfdData as object - PostZREport will convert it to array internally
            $postResponse = $this->PostZREport(
                $vfdData,
                $date,
                $znumber,
                $time,
                $dailySales ?? 0,
                $cumulativeTotal ?? 0,
                $totalTransactions ?? 0,
                $netAmount,
                $taxAmount,
                $emoneyTotal ?? 0,
                $cashTotal ?? 0
            );

            $finalTime = round(microtime(true) - $startTime, 2);
            Log::info('Z-Report Batch: Completed', [
                'znumber' => $znumber,
                'ackcode' => $postResponse['data']['ackcode'] ?? null,
                'time_seconds' => $finalTime
            ]);

            // Return response with Z-report data and posting result
            return response()->json([
                'success' => true,
                'message' => 'Z-report generated and posted successfully',
                'zreport' => $zreportData,
                'post_response' => $postResponse
            ]);

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $isTimeout = strpos($errorMsg, 'maximum statement execution time exceeded') !== false || 
                        strpos($errorMsg, 'Query execution was interrupted') !== false ||
                        strpos($errorMsg, 'timeout') !== false ||
                        strpos($errorMsg, 'Maximum execution time') !== false;
            
            Log::error('Z-Report Batch: Error occurred', [
                'error' => $errorMsg,
                'is_timeout' => $isTimeout,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $errorMsg,
                'is_timeout' => $isTimeout,
                'suggestion' => $isTimeout 
                    ? 'Query timed out. Check logs for which query (daily or cumulative) timed out. Consider optimizing database indexes on transactions.created_at column.'
                    : 'An error occurred while generating Z-report. Check logs for details.'
            ], 500);
        }
    }

    /**
     * Batch Post All Prepared Z-Reports
     * Finds all zreports with status "prepared" and posts them one by one
     * 
     * POST /api/z-report/batch-post-all
     * Payload: {} (no parameters needed, or optional filters)
     */
    public function batchPostAll(Request $request)
    {
        set_time_limit(3600); // 60 minutes for batch processing
        ini_set('max_execution_time', 3600);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Batch Post All: Starting', ['timestamp' => now()]);

            // Optional filters
            $limit = $request->input('limit'); // Optional: limit number of zreports to process
            $dateFrom = $request->input('date_from'); // Optional: filter by date from
            $dateTo = $request->input('date_to'); // Optional: filter by date to

            // Helper function to check if status indicates a token error (retryable)
            $isTokenError = function($status) {
                if (empty($status)) return false;
                $statusLower = strtolower($status);
                return (
                    stripos($statusLower, 'invalid token') !== false ||
                    stripos($statusLower, 'token expired') !== false ||
                    stripos($statusLower, 'bearer') !== false && stripos($statusLower, 'invalid') !== false
                );
            };
            
            // Find all prepared zreports OR zreports with token errors (retryable) using default connection
            $query = Zreport::where(function($q) use ($isTokenError) {
                    $q->where('ackmsg', 'prepared')
                      ->orWhereRaw('LOWER(ackmsg) LIKE ?', ['%invalid token%'])
                      ->orWhereRaw('LOWER(ackmsg) LIKE ?', ['%token expired%'])
                      ->orWhereRaw('LOWER(ackmsg) LIKE ?', ['%bearer%']);
                })
                ->whereNotNull('gross')
                ->whereNotNull('dailytotalamount')
                ->whereNotNull('netamount')
                ->whereNotNull('taxamount')
                ->orderBy('date', 'asc'); // Process oldest first

            // Apply optional filters
            if ($dateFrom) {
                $query->where('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->where('date', '<=', $dateTo);
            }
            if ($limit && is_numeric($limit)) {
                $query->limit((int)$limit);
            }

            $preparedZreports = $query->get();

            if ($preparedZreports->isEmpty()) {
                Log::info('Z-Report Batch Post All: No prepared zreports found');
                return response()->json([
                    'success' => true,
                    'message' => 'No prepared zreports found to post',
                    'total_found' => 0,
                    'processed' => 0,
                    'results' => []
                ]);
            }

            Log::info('Z-Report Batch Post All: Found prepared zreports', [
                'total_count' => $preparedZreports->count()
            ]);

            $results = [];
            $successCount = 0;
            $failureCount = 0;

            // Get VFD registration data once using default connection (used for all posts)
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();
            
            if (!$vfdData) {
                Log::error('Z-Report Batch Post All: VFD registration data not found');
                return response()->json([
                    'success' => false,
                    'error' => 'VFD registration data not found. Please ensure vfd_registration table has data.'
                ], 404);
            }
            

            // Process each prepared zreport
            foreach ($preparedZreports as $index => $zreport) {
                $itemStartTime = microtime(true);
                $znumber = $zreport->znumber;
                $currentAckmsg = $zreport->ackmsg ?? null;
                
                // If it's a token error, refresh token and reset status
                if ($isTokenError($currentAckmsg)) {
                    Log::info('Z-Report Batch Post All: Token error detected, refreshing token', ['znumber' => $znumber]);
                    
                    $tokenData = ZReportService::requestToken();
                    if (!$tokenData) {
                        $failureCount++;
                        $results[] = [
                            'znumber' => $znumber,
                            'date' => is_string($zreport->date) ? $zreport->date : (is_object($zreport->date) && method_exists($zreport->date, 'format') ? $zreport->date->format('Y-m-d') : null),
                            'status' => 'failed',
                            'message' => 'Failed to refresh token',
                            'time_seconds' => round(microtime(true) - $itemStartTime, 2)
                        ];
                        continue;
                    }
                    
                    // Update token
                    $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
                    if ($vfdToken) {
                        $vfdToken->token = $tokenData['access_token'] ?? null;
                        $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                        $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                        $vfdToken->created_at = date('Y-m-d H:i:s');
                        $vfdToken->created_by = 1;
                        $vfdToken->save();
                    }
                    
                    // Reset status
                    Zreport::where('znumber', $znumber)->update([
                        'ackmsg' => 'prepared',
                        'ackcode' => null
                    ]);
                    
                    // Reload zreport
                    $zreport = Zreport::where('znumber', $znumber)->first();
                }

                try {
            // Prepare data for posting
            $date = $this->formatDateTime($zreport->date, null, 'Y-m-d');
            $time = $this->formatDateTime($zreport->time, '23:59:59', 'H:i:s');
            
            $dailySales = $zreport->dailytotalamount ?? 0;
                    $cumulativeTotal = $zreport->gross ?? 0;
                    $totalTransactions = $zreport->ticketfiscal ?? 0;
                    $netAmount = $zreport->netamount ?? 0;
                    $taxAmount = $zreport->taxamount ?? 0;
                    $cashTotal = $zreport->cashamount ?? 0;
                    $emoneyTotal = $zreport->emoney_amount ?? 0;

                    // Post to TRA
                    $postResponse = $this->PostZREport(
                        $vfdData,
                        $date,
                        $znumber,
                        $time,
                        $dailySales,
                        $cumulativeTotal,
                        $totalTransactions,
                        $netAmount,
                        $taxAmount,
                        $emoneyTotal,
                        $cashTotal
                    );

                    $itemTime = round(microtime(true) - $itemStartTime, 2);

                    // Check if posting was successful
                    if (isset($postResponse['data']['ackcode']) && $postResponse['data']['ackcode'] != null) {
                        $successCount++;
                        $results[] = [
                            'znumber' => $znumber,
                            'date' => $date,
                            'status' => 'success',
                            'ackcode' => $postResponse['data']['ackcode'] ?? null,
                            'ackmsg' => $postResponse['data']['ackmsg'] ?? null,
                            'message' => $postResponse['message'] ?? 'Posted successfully',
                            'time_seconds' => $itemTime
                        ];

                    } else {
                        $failureCount++;
                        $results[] = [
                            'znumber' => $znumber,
                            'date' => $date,
                            'status' => 'failed',
                            'message' => $postResponse['message'] ?? 'Failed to post',
                            'error' => $postResponse['data'] ?? null,
                            'time_seconds' => $itemTime
                        ];

                    }

                } catch (\Exception $e) {
                    $failureCount++;
                    $itemTime = round(microtime(true) - $itemStartTime, 2);
                    
                    $results[] = [
                        'znumber' => $znumber,
                        'date' => $this->formatDateTime($zreport->date, null, 'Y-m-d'),
                        'status' => 'error',
                        'message' => 'Exception occurred: ' . $e->getMessage(),
                        'time_seconds' => $itemTime
                    ];

                    Log::error('Z-Report Batch Post All: Exception', [
                        'znumber' => $znumber,
                        'error' => $e->getMessage()
                    ]);
                }

                // Small delay between posts to avoid overwhelming the API
                if ($index < $preparedZreports->count() - 1) {
                    usleep(500000); // 0.5 second delay
                }
            }

            $totalTime = round(microtime(true) - $startTime, 2);
            
            Log::info('Z-Report Batch Post All: Completed', [
                'total' => $preparedZreports->count(),
                'success' => $successCount,
                'failed' => $failureCount,
                'time_seconds' => $totalTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch posting completed',
                'summary' => [
                    'total_found' => $preparedZreports->count(),
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'total_time_seconds' => $totalTime
                ],
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Z-Report Batch Post All: Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch Prepare Multiple Z-Reports
     * Prepares multiple znumbers sequentially (queue-wise)
     * 
     * POST /api/z-report/batch-prepare
     * Payload: { "znumbers": ["20210422", "20210423", "20210424"] }
     */
    public function batchPrepare(Request $request)
    {
        set_time_limit(3600); // 60 minutes for batch processing
        ini_set('max_execution_time', 3600);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Batch Prepare: Starting');

            // Request data - array of znumbers required
            $znumbers = $request->input('znumbers');
            
            if (!$znumbers || !is_array($znumbers) || empty($znumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'znumbers array is required',
                    'suggestion' => 'Provide znumbers as an array in YYYYMMDD format (e.g., ["20210422", "20210423"])'
                ], 400);
            }

            // Validate all znumbers format
            $invalidZnumbers = [];
            foreach ($znumbers as $znumber) {
                if (!preg_match('/^\d{8}$/', $znumber)) {
                    $invalidZnumbers[] = $znumber;
                }
            }

            if (!empty($invalidZnumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid znumber format(s)',
                    'invalid_znumbers' => $invalidZnumbers,
                    'suggestion' => 'All znumbers must be in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            $prepareResults = [];
            $prepareSuccessCount = 0;
            $prepareFailureCount = 0;

            // Prepare all znumbers sequentially (queue-wise)
            Log::info('Z-Report Batch Prepare: Preparing znumbers', [
                'total_count' => count($znumbers),
                'znumbers' => $znumbers
            ]);

            foreach ($znumbers as $index => $znumber) {
                $prepareStartTime = microtime(true);
                
                try {
                    $prepareResult = $this->prepareSingleZReport($znumber);
                    
                    if ($prepareResult['success']) {
                        $prepareSuccessCount++;
                        $prepareResults[] = [
                            'znumber' => $znumber,
                            'status' => 'prepared',
                            'message' => 'Successfully prepared',
                            'time_seconds' => round(microtime(true) - $prepareStartTime, 2),
                            'calculation_method' => $prepareResult['calculation_method'] ?? null
                        ];
                        Log::info('Z-Report Batch Prepare: Prepared', [
                            'znumber' => $znumber,
                            'index' => $index + 1,
                            'total' => count($znumbers)
                        ]);
                    } else {
                        $prepareFailureCount++;
                        $prepareResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => $prepareResult['error'] ?? 'Preparation failed',
                            'time_seconds' => round(microtime(true) - $prepareStartTime, 2)
                        ];
                        Log::error('Z-Report Batch Prepare: Preparation failed', [
                            'znumber' => $znumber,
                            'error' => $prepareResult['error'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    $prepareFailureCount++;
                    $prepareResults[] = [
                        'znumber' => $znumber,
                        'status' => 'error',
                        'message' => 'Exception: ' . $e->getMessage(),
                        'time_seconds' => round(microtime(true) - $prepareStartTime, 2)
                    ];
                    Log::error('Z-Report Batch Prepare: Exception during preparation', [
                        'znumber' => $znumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $totalTime = round(microtime(true) - $startTime, 2);

            Log::info('Z-Report Batch Prepare: Completed', [
                'total_znumbers' => count($znumbers),
                'success' => $prepareSuccessCount,
                'failed' => $prepareFailureCount,
                'total_time_seconds' => $totalTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch preparation completed',
                'summary' => [
                    'total_znumbers' => count($znumbers),
                    'success' => $prepareSuccessCount,
                    'failed' => $prepareFailureCount,
                    'total_time_seconds' => $totalTime
                ],
                'results' => $prepareResults
            ]);

        } catch (\Exception $e) {
            Log::error('Z-Report Batch Prepare: Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch Post Multiple Z-Reports
     * Posts multiple znumbers sequentially
     * 
     * POST /api/z-report/batch-post
     * Payload: { "znumbers": ["20210422", "20210423", "20210424"] }
     */
    public function batchPost(Request $request)
    {
        set_time_limit(3600); // 60 minutes for batch processing
        ini_set('max_execution_time', 3600);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Batch Post: Starting');

            // Request data - array of znumbers required
            $znumbers = $request->input('znumbers');
            
            if (!$znumbers || !is_array($znumbers) || empty($znumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'znumbers array is required',
                    'suggestion' => 'Provide znumbers as an array in YYYYMMDD format (e.g., ["20210422", "20210423"])'
                ], 400);
            }

            // Validate all znumbers format
            $invalidZnumbers = [];
            foreach ($znumbers as $znumber) {
                if (!preg_match('/^\d{8}$/', $znumber)) {
                    $invalidZnumbers[] = $znumber;
                }
            }

            if (!empty($invalidZnumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid znumber format(s)',
                    'invalid_znumbers' => $invalidZnumbers,
                    'suggestion' => 'All znumbers must be in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            // Get VFD registration data once (used for all posts)
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();
            
            if (!$vfdData) {
                return response()->json([
                    'success' => false,
                    'error' => 'VFD registration data not found. Please ensure vfd_registration table has data.'
                ], 404);
            }

            // Helper function to check if status indicates a token error (retryable)
            $isTokenError = function($status) {
                if (empty($status)) return false;
                $statusLower = strtolower($status);
                return (
                    stripos($statusLower, 'invalid token') !== false ||
                    stripos($statusLower, 'token expired') !== false ||
                    stripos($statusLower, 'bearer') !== false && stripos($statusLower, 'invalid') !== false
                );
            };

            $postResults = [];
            $postSuccessCount = 0;
            $postFailureCount = 0;

            Log::info('Z-Report Batch Post: Posting znumbers', [
                'total_count' => count($znumbers),
                'znumbers' => $znumbers
            ]);

            // Post each znumber
            foreach ($znumbers as $index => $znumber) {
                $postStartTime = microtime(true);
                
                try {
                    // Read pre-calculated data from zreport table
                    $zreport = Zreport::where('znumber', $znumber)->first();
                    
                    if (!$zreport) {
                        $postFailureCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => 'Z-report not found. Please run prepare first.',
                            'time_seconds' => round(microtime(true) - $postStartTime, 2)
                        ];
                        Log::error('Z-Report Batch Post: Z-report not found', ['znumber' => $znumber]);
                        continue;
                    }

                    // Check if status is "prepared" or a token error (retryable)
                    $currentAckmsg = $zreport->ackmsg ?? null;
                    
                    if ($currentAckmsg !== 'prepared' && !$isTokenError($currentAckmsg)) {
                        $postFailureCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => 'Z-report not in prepared status. Current status: ' . ($currentAckmsg ?? 'null'),
                            'time_seconds' => round(microtime(true) - $postStartTime, 2)
                        ];
                        Log::error('Z-Report Batch Post: Not in prepared status', [
                            'znumber' => $znumber,
                            'current_status' => $currentAckmsg
                        ]);
                        continue;
                    }

                    // If it's a token error, refresh token and reset status
                    if ($isTokenError($currentAckmsg)) {
                        Log::info('Z-Report Batch Post: Token error detected, refreshing token', ['znumber' => $znumber]);
                        
                        $tokenData = ZReportService::requestToken();
                        if (!$tokenData) {
                            $postFailureCount++;
                            $postResults[] = [
                                'znumber' => $znumber,
                                'status' => 'failed',
                                'message' => 'Failed to refresh token',
                                'time_seconds' => round(microtime(true) - $postStartTime, 2)
                            ];
                            continue;
                        }
                        
                        // Update token
                        $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
                        if ($vfdToken) {
                            $vfdToken->token = $tokenData['access_token'] ?? null;
                            $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                            $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                            $vfdToken->created_at = date('Y-m-d H:i:s');
                            $vfdToken->created_by = 1;
                            $vfdToken->save();
                        }
                        
                        // Reset status
                        Zreport::where('znumber', $znumber)->update([
                            'ackmsg' => 'prepared',
                            'ackcode' => null
                        ]);
                        
                        // Reload zreport
                        $zreport = Zreport::where('znumber', $znumber)->first();
                    }

                    // Prepare data for posting
                    $date = $this->formatDateTime($zreport->date, null, 'Y-m-d');
                    $time = $this->formatDateTime($zreport->time, '23:59:59', 'H:i:s');
                    
                    $dailySales = $zreport->dailytotalamount ?? 0;
                    $cumulativeTotal = $zreport->gross ?? 0;
                    $totalTransactions = $zreport->ticketfiscal ?? 0;
                    $netAmount = $zreport->netamount ?? 0;
                    $taxAmount = $zreport->taxamount ?? 0;
                    $cashTotal = $zreport->cashamount ?? 0;
                    $emoneyTotal = $zreport->emoney_amount ?? 0;

                    // Post to TRA
                    $postResponse = $this->PostZREport(
                        $vfdData,
                        $date,
                        $znumber,
                        $time,
                        $dailySales ?? 0,
                        $cumulativeTotal ?? 0,
                        $totalTransactions ?? 0,
                        $netAmount,
                        $taxAmount,
                        $emoneyTotal ?? 0,
                        $cashTotal ?? 0
                    );

                    $postTime = round(microtime(true) - $postStartTime, 2);

                    if (isset($postResponse['data']['ackcode']) && $postResponse['data']['ackcode'] != null) {
                        $postSuccessCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'success',
                            'ackcode' => $postResponse['data']['ackcode'] ?? null,
                            'ackmsg' => $postResponse['data']['ackmsg'] ?? null,
                            'message' => $postResponse['message'] ?? 'Posted successfully',
                            'time_seconds' => $postTime
                        ];
                        Log::info('Z-Report Batch Post: Posted successfully', [
                            'znumber' => $znumber,
                            'ackcode' => $postResponse['data']['ackcode'] ?? null
                        ]);
                    } else {
                        $postFailureCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => $postResponse['message'] ?? 'Failed to post',
                            'time_seconds' => $postTime
                        ];
                        Log::error('Z-Report Batch Post: Failed to post', [
                            'znumber' => $znumber,
                            'message' => $postResponse['message'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    $postFailureCount++;
                    $postResults[] = [
                        'znumber' => $znumber,
                        'status' => 'error',
                        'message' => 'Exception: ' . $e->getMessage(),
                        'time_seconds' => round(microtime(true) - $postStartTime, 2)
                    ];
                    Log::error('Z-Report Batch Post: Exception during posting', [
                        'znumber' => $znumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $totalTime = round(microtime(true) - $startTime, 2);

            Log::info('Z-Report Batch Post: Completed', [
                'total_znumbers' => count($znumbers),
                'success' => $postSuccessCount,
                'failed' => $postFailureCount,
                'total_time_seconds' => $totalTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch posting completed',
                'summary' => [
                    'total_znumbers' => count($znumbers),
                    'success' => $postSuccessCount,
                    'failed' => $postFailureCount,
                    'total_time_seconds' => $totalTime
                ],
                'results' => $postResults
            ]);

        } catch (\Exception $e) {
            Log::error('Z-Report Batch Post: Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch Prepare and Post Multiple Z-Reports
     * Prepares multiple znumbers sequentially (queue-wise), then posts all at once
     * 
     * POST /api/z-report/batch-prepare-and-post
     * Payload: { "znumbers": ["20210422", "20210423", "20210424"] }
     */
    public function batchPrepareAndPost(Request $request)
    {
        set_time_limit(3600); // 60 minutes for batch processing
        ini_set('max_execution_time', 3600);

        try {
            $startTime = microtime(true);
            Log::info('Z-Report Batch Prepare and Post: Starting');

            // Request data - array of znumbers required
            $znumbers = $request->input('znumbers');
            
            if (!$znumbers || !is_array($znumbers) || empty($znumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'znumbers array is required',
                    'suggestion' => 'Provide znumbers as an array in YYYYMMDD format (e.g., ["20210422", "20210423"])'
                ], 400);
            }

            // Validate all znumbers format
            $invalidZnumbers = [];
            foreach ($znumbers as $znumber) {
                if (!preg_match('/^\d{8}$/', $znumber)) {
                    $invalidZnumbers[] = $znumber;
                }
            }

            if (!empty($invalidZnumbers)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid znumber format(s)',
                    'invalid_znumbers' => $invalidZnumbers,
                    'suggestion' => 'All znumbers must be in YYYYMMDD format (e.g., "20210422")'
                ], 400);
            }

            $prepareResults = [];
            $prepareSuccessCount = 0;
            $prepareFailureCount = 0;

            // Phase 1: Prepare all znumbers sequentially (queue-wise)
            Log::info('Z-Report Batch Prepare and Post: Phase 1 - Preparing znumbers', [
                'total_count' => count($znumbers),
                'znumbers' => $znumbers
            ]);

            foreach ($znumbers as $index => $znumber) {
                $prepareStartTime = microtime(true);
                
                try {
                    $prepareResult = $this->prepareSingleZReport($znumber);
                    
                    if ($prepareResult['success']) {
                        $prepareSuccessCount++;
                        $prepareResults[] = [
                            'znumber' => $znumber,
                            'status' => 'prepared',
                            'message' => 'Successfully prepared',
                            'time_seconds' => round(microtime(true) - $prepareStartTime, 2),
                            'calculation_method' => $prepareResult['calculation_method'] ?? null
                        ];
                        Log::info('Z-Report Batch Prepare and Post: Prepared', [
                            'znumber' => $znumber,
                            'index' => $index + 1,
                            'total' => count($znumbers)
                        ]);
                    } else {
                        $prepareFailureCount++;
                        $prepareResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => $prepareResult['error'] ?? 'Preparation failed',
                            'time_seconds' => round(microtime(true) - $prepareStartTime, 2)
                        ];
                        Log::error('Z-Report Batch Prepare and Post: Preparation failed', [
                            'znumber' => $znumber,
                            'error' => $prepareResult['error'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    $prepareFailureCount++;
                    $prepareResults[] = [
                        'znumber' => $znumber,
                        'status' => 'error',
                        'message' => 'Exception: ' . $e->getMessage(),
                        'time_seconds' => round(microtime(true) - $prepareStartTime, 2)
                    ];
                    Log::error('Z-Report Batch Prepare and Post: Exception during preparation', [
                        'znumber' => $znumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $prepareTime = round(microtime(true) - $startTime, 2);
            Log::info('Z-Report Batch Prepare and Post: Phase 1 completed', [
                'total' => count($znumbers),
                'success' => $prepareSuccessCount,
                'failed' => $prepareFailureCount,
                'time_seconds' => $prepareTime
            ]);

            // Phase 2: Post all prepared znumbers at once
            Log::info('Z-Report Batch Prepare and Post: Phase 2 - Posting all prepared znumbers');

            $postResults = [];
            $postSuccessCount = 0;
            $postFailureCount = 0;

            // Get VFD registration data once (used for all posts)
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();
            
            if (!$vfdData) {
                return response()->json([
                    'success' => false,
                    'error' => 'VFD registration data not found. Please ensure vfd_registration table has data.',
                    'prepare_results' => $prepareResults
                ], 404);
            }

            // Post each successfully prepared znumber
            foreach ($znumbers as $znumber) {
                // Only post if preparation was successful
                $prepareResult = collect($prepareResults)->firstWhere('znumber', $znumber);
                if (!$prepareResult || $prepareResult['status'] !== 'prepared') {
                    $postResults[] = [
                        'znumber' => $znumber,
                        'status' => 'skipped',
                        'message' => 'Skipped - preparation failed',
                        'time_seconds' => 0
                    ];
                    continue;
                }

                $postStartTime = microtime(true);
                
                try {
                    // Read pre-calculated data from zreport table
                    $zreport = Zreport::where('znumber', $znumber)->first();
                    
                    if (!$zreport || $zreport->ackmsg !== 'prepared') {
                        $postFailureCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => 'Z-report not found or not in prepared status',
                            'time_seconds' => round(microtime(true) - $postStartTime, 2)
                        ];
                        continue;
                    }

                    // Prepare data for posting
                    $date = $this->formatDateTime($zreport->date, null, 'Y-m-d');
                    $time = $this->formatDateTime($zreport->time, '23:59:59', 'H:i:s');
                    
                    $dailySales = $zreport->dailytotalamount ?? 0;
                    $cumulativeTotal = $zreport->gross ?? 0;
                    $totalTransactions = $zreport->ticketfiscal ?? 0;
                    $netAmount = $zreport->netamount ?? 0;
                    $taxAmount = $zreport->taxamount ?? 0;
                    $cashTotal = $zreport->cashamount ?? 0;
                    $emoneyTotal = $zreport->emoney_amount ?? 0;

                    // Post to TRA
                    $postResponse = $this->PostZREport(
                        $vfdData,
                        $date,
                        $znumber,
                        $time,
                        $dailySales ?? 0,
                        $cumulativeTotal ?? 0,
                        $totalTransactions ?? 0,
                        $netAmount,
                        $taxAmount,
                        $emoneyTotal ?? 0,
                        $cashTotal ?? 0
                    );

                    $postTime = round(microtime(true) - $postStartTime, 2);

                    if (isset($postResponse['data']['ackcode']) && $postResponse['data']['ackcode'] != null) {
                        $postSuccessCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'success',
                            'ackcode' => $postResponse['data']['ackcode'] ?? null,
                            'ackmsg' => $postResponse['data']['ackmsg'] ?? null,
                            'message' => $postResponse['message'] ?? 'Posted successfully',
                            'time_seconds' => $postTime
                        ];
                    } else {
                        $postFailureCount++;
                        $postResults[] = [
                            'znumber' => $znumber,
                            'status' => 'failed',
                            'message' => $postResponse['message'] ?? 'Failed to post',
                            'time_seconds' => $postTime
                        ];
                    }
                } catch (\Exception $e) {
                    $postFailureCount++;
                    $postResults[] = [
                        'znumber' => $znumber,
                        'status' => 'error',
                        'message' => 'Exception: ' . $e->getMessage(),
                        'time_seconds' => round(microtime(true) - $postStartTime, 2)
                    ];
                    Log::error('Z-Report Batch Prepare and Post: Exception during posting', [
                        'znumber' => $znumber,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $totalTime = round(microtime(true) - $startTime, 2);

            Log::info('Z-Report Batch Prepare and Post: Completed', [
                'total_znumbers' => count($znumbers),
                'prepare_success' => $prepareSuccessCount,
                'prepare_failed' => $prepareFailureCount,
                'post_success' => $postSuccessCount,
                'post_failed' => $postFailureCount,
                'total_time_seconds' => $totalTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Batch prepare and post completed',
                'summary' => [
                    'total_znumbers' => count($znumbers),
                    'prepare_success' => $prepareSuccessCount,
                    'prepare_failed' => $prepareFailureCount,
                    'post_success' => $postSuccessCount,
                    'post_failed' => $postFailureCount,
                    'total_time_seconds' => $totalTime
                ],
                'prepare_results' => $prepareResults,
                'post_results' => $postResults
            ]);

        } catch (\Exception $e) {
            Log::error('Z-Report Batch Prepare and Post: Error occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to prepare a single znumber (extracted from prepareZReport)
     * Used by batchPrepareAndPost
     */
    private function prepareSingleZReport($znumber)
    {
        try {
            // Derive date, time, and zdate from znumber
            $year = substr($znumber, 0, 4);
            $month = substr($znumber, 4, 2);
            $day = substr($znumber, 6, 2);
            $date = "{$year}-{$month}-{$day}";
            $time = '23:59:59';
            $zdate = "DATE(created_at) = '{$date}'";

            // Check if already prepared
            $existingZreport = Zreport::where('znumber', $znumber)->first();
            
            if ($existingZreport && strtolower($existingZreport->ackmsg ?? '') === 'success') {
                return [
                    'success' => true,
                    'message' => 'Already posted successfully',
                    'calculation_method' => 'already_posted'
                ];
            }

            // Get VFD registration data
            $vfdData = DB::table('vfd_registration')
                ->select(
                    'name', 'city', 'mobile', 'address', 'username',
                    'uin', 'vrn', 'password', 'receiptcode',
                    'tin', 'reg_id', 'serila', 'routingkey',
                    'taxoffice', DB::raw('DATE(created_at) as date')
                )
                ->first();
            
            if (!$vfdData) {
                return [
                    'success' => false,
                    'error' => 'VFD registration data not found'
                ];
            }

            // Get daily data
            $dailyData = DB::table('transactions')
                ->selectRaw("
                    COUNT(gc) as total_trans,
                    SUM(charged_amount) as daily_total,
                    SUM(CASE WHEN payment_type = 'CASH' THEN charged_amount ELSE 0 END) as cash_total,
                    SUM(CASE WHEN payment_type = 'EMONEY' THEN charged_amount ELSE 0 END) as emoney_total,
                    SUM(ROUND(charged_amount / 1.18, 2)) as net_amount,
                    SUM(charged_amount - ROUND(charged_amount / 1.18, 2)) as tax_amount
                ")
                ->whereRaw($zdate)
                ->first();

            $dailySales = $dailyData->daily_total ?? 0;
            $cashTotal = $dailyData->cash_total ?? 0;
            $emoneyTotal = $dailyData->emoney_total ?? 0;
            $totalTransactions = $dailyData->total_trans ?? 0;
            $netAmount = $dailyData->net_amount ?? 0;
            $taxAmount = $dailyData->tax_amount ?? 0;

            // Smart Cumulative Calculation
            $previousZreport = Zreport::where('date', '<', $date)
                ->whereNotNull('gross')
                ->orderBy('date', 'desc')
                ->first();

            $cumulativeTotal = 0;
            $calculationMethod = '';

            if ($previousZreport) {
                $previousGross = $previousZreport->gross ?? 0;
                $cumulativeTotal = $previousGross + $dailySales;
                $calculationMethod = 'previous_gross_plus_daily';
            } else {
                DB::statement("SET SESSION max_execution_time = 1200000");
                DB::statement("SET SESSION innodb_lock_wait_timeout = 1200");
                
                $cumulativeTotal = DB::table('transactions')
                    ->whereRaw("DATE(created_at) <= ?", [$date])
                    ->sum('charged_amount');

                $calculationMethod = 'calculated_from_start';
            }

            if ($cumulativeTotal === null) {
                $cumulativeTotal = 0;
            }

            // Save to zreport table
            $zreportData = [
                'date' => $date,
                'time' => $time,
                'vrn' => $vfdData->vrn ?? null,
                'tin' => $vfdData->tin ?? null,
                'name' => $vfdData->name ?? null,
                'taxoffice' => $vfdData->taxoffice ?? null,
                'regid' => $vfdData->reg_id ?? null,
                'znumber' => $znumber,
                'efdserial' => $vfdData->serila ?? null,
                'registrationdate' => $vfdData->date ?? null,
                'user' => $vfdData->uin ?? null,
                'simimsi' => 'WEBAPI',
                'dailytotalamount' => $dailySales ?? 0,
                'gross' => $cumulativeTotal ?? 0,
                'corrections' => 0.00,
                'discounts' => 0.00,
                'surcharges' => 0.00,
                'ticketsvoid' => 0,
                'ticketsvoidtotal' => 0,
                'ticketfiscal' => $totalTransactions ?? 0,
                'ticketsnonfiscal' => 0,
                'vatrate' => 'A-18.00',
                'netamount' => $netAmount,
                'taxamount' => $taxAmount,
                'cashamount' => $cashTotal ?? 0,
                'emoney_amount' => $emoneyTotal ?? 0,
                'emoney_type' => 'EMONEY',
                'cash_type' => 'CASH',
                'vatchangenum' => 0,
                'headchangenum' => 0,
                'city' => $vfdData->city ?? null,
                'mobile' => $vfdData->mobile ?? null,
                'address' => $vfdData->address ?? null,
                'ackcode' => null,
                'ackmsg' => 'prepared'
            ];

            if ($existingZreport) {
                $existingZreport->update($zreportData);
            } else {
                Zreport::create($zreportData);
            }

            return [
                'success' => true,
                'message' => 'Z-report data prepared and saved',
                'calculation_method' => $calculationMethod
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Post Z-Report to TRA VFD API
     * Matches Yii2 PostZREport method signature and flow
     * 
     * @param object $vfdData VFD registration data
     * @param string $date Report date
     * @param string $znumber Z-number
     * @param string $time Report time
     * @param float $sumSalesPrevDay Daily total amount
     * @param float $cumulativeTotal Cumulative total
     * @param int $totalNoTrans Total number of transactions
     * @param float $nettamount Net amount
     * @param float $taxamount Tax amount
     * @param float $emoneyTotal E-money total
     * @param float $cashTotal Cash total
     * @return array
     */
    private function PostZREport($vfdData, $date, $znumber, $time, $sumSalesPrevDay, $cumulativeTotal, $totalNoTrans,
                                 $nettamount, $taxamount, $emoneyTotal, $cashTotal)
    {
        $postStartTime = microtime(true);
        Log::info('PostZREport: Starting', ['znumber' => $znumber]);

        try {
            // Get VFD token from database (match Yii2: orderBy('created_at DESC'))
            $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
            
            if (!$vfdToken) {
                Log::info('PostZREport: No token found, requesting new token');
                
                $tokenData = ZReportService::requestToken();
                if (!$tokenData) {
                    Log::error('PostZREport: Failed to request new token');
                    return ['message' => 'Failed to Generate token', 'data' => []];
                }
                
                $vfdToken = new VfdToken();
                $vfdToken->token = $tokenData['access_token'] ?? null;
                $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                $vfdToken->created_by = 1;
                $vfdToken->created_at = date('Y-m-d H:i:s');
                $vfdToken->save();
                
                $token = $vfdToken->token;
            } else {
                // Check token expiry (expires_in is in SECONDS)
                $tokenGenAt = $vfdToken->created_at;
                $expirDuration = $vfdToken->expires_in ?? 3600;
                $dateInSec = strtotime($tokenGenAt);
                $expireDate = date('Y-m-d H:i:s', $dateInSec + $expirDuration);
                $currentDate = strtotime(date('Y-m-d H:i:s'));
                $expireDateInSec = strtotime($expireDate);
                
                if ($currentDate > $expireDateInSec) {
                    Log::info('PostZREport: Token expired, refreshing');
                    
                    $tokenData = ZReportService::requestToken();
                    if (!$tokenData) {
                        Log::error('PostZREport: Failed to refresh token');
                        return ['message' => 'Failed to Generate token', 'data' => []];
                    }
                    
                    // Update existing token (match Yii2)
                    $vfdToken->created_by = 1;
                    $vfdToken->token = $tokenData['access_token'] ?? null;
                    $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                    $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                    $vfdToken->created_at = date('Y-m-d H:i:s');
                    $vfdToken->save();
                    
                    $token = $vfdToken->token;
                } else {
                    $token = $vfdToken->token;
                }
            }
            
            // Convert vfdData to array
            if (is_array($vfdData)) {
                $vfdDataArray = $vfdData;
            } elseif (is_object($vfdData)) {
                $vfdDate = $this->formatDateTime($vfdData->date ?? null, null, 'Y-m-d');
                
                $vfdDataArray = [
                    'vrn' => $vfdData->vrn ?? null,
                    'tin' => $vfdData->tin ?? null,
                    'name' => $vfdData->name ?? null,
                    'city' => $vfdData->city ?? null,
                    'mobile' => $vfdData->mobile ?? null,
                    'address' => $vfdData->address ?? null,
                    'taxoffice' => $vfdData->taxoffice ?? null,
                    'reg_id' => $vfdData->reg_id ?? null,
                    'serila' => $vfdData->serila ?? null,
                    'date' => $vfdDate,
                    'uin' => $vfdData->uin ?? null,
                ];
            } else {
                Log::error('PostZREport: Invalid vfdData type', ['type' => gettype($vfdData)]);
                return ['message' => 'Invalid VFD data format', 'data' => []];
            }
            
            $response = ZReportService::postZReport(
                $vfdDataArray,
                $date,
                $time,
                $znumber,
                $sumSalesPrevDay,
                $cumulativeTotal,
                $totalNoTrans,
                $nettamount,
                $taxamount,
                $cashTotal,
                $emoneyTotal
            );
            
            $totalTime = round(microtime(true) - $postStartTime, 2);
            
            if (isset($response['status']) && $response['status'] == 1) {
                Log::info('PostZREport: Success', [
                    'znumber' => $znumber,
                    'ackcode' => $response['data']['ackcode'] ?? null,
                    'time_seconds' => $totalTime
                ]);
                
                return [
                    'message' => $response['message'] ?? 'Successfully sent and Logged',
                    'data' => $response['data'] ?? []
                ];
            } else {
                Log::error('PostZREport: Failed', [
                    'znumber' => $znumber,
                    'message' => $response['message'] ?? 'Failed to send Z-Report',
                    'time_seconds' => $totalTime
                ]);
                
                return [
                    'message' => $response['message'] ?? 'Failed to send Z-Report',
                    'data' => $response['data'] ?? $response
                ];
            }
            
        } catch (\Exception $e) {
            $totalTime = round(microtime(true) - $postStartTime, 2);
            Log::error('PostZREport: Exception occurred', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_time_seconds' => $totalTime,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'message' => 'Failed to send Z-Report: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
}
