<?php

namespace App\Helpers;

use App\Models\VfdToken;
use App\Models\VfdZreport;
use App\Models\Zreport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZReportService
{
    /**
     * Request a new VFD token from external API
     * 
     * @return array|null
     */
    public static function requestToken()
    {
        $vfdApiUrl = config('params.api_urls.vfd.default') ?: config('params.api_urls.vfd.production');
        
        if (empty($vfdApiUrl)) {
            Log::error('ZReportService::requestToken: VFD API URL not configured');
            return null;
        }
        
        // Get username and password from vfd_registration table
        $vfdData = DB::table('vfd_registration')
            ->select('username', 'password')
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$vfdData || empty($vfdData->username) || empty($vfdData->password)) {
            Log::error('ZReportService::requestToken: VFD registration credentials not found');
            return null;
        }
        
        try {
            $response = Http::timeout(30)
                ->retry(2, 100)
                ->post($vfdApiUrl . 'receipt/request-token', [
                    'username' => $vfdData->username,
                    'password' => $vfdData->password,
                ]);
            
            if ($response->successful()) {
                $responseJson = $response->json();
                
                if (isset($responseJson['access_token'])) {
                    Log::info('ZReportService::requestToken: Token received', [
                        'expires_in' => $responseJson['expires_in'] ?? null
                    ]);
                } else {
                    Log::warning('ZReportService::requestToken: No access_token in response');
                }
                
                return $responseJson;
            }
            
            Log::error('ZReportService::requestToken: Failed', [
                'status_code' => $response->status(),
                'response_body' => $response->body()
            ]);
            
            return null;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('ZReportService::requestToken: Connection timeout while requesting VFD token', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('ZReportService::requestToken: Exception while requesting VFD token', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    /**
     * Get or refresh VFD token
     * 
     * @return string|null
     */
    public static function getToken()
    {
        // Match Yii2: orderBy('created_at DESC')->limit(1)
        $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
        
        if (!$vfdToken) {
            // No token exists, request a new one
            $tokenData = self::requestToken();
            if (!$tokenData) {
                return null;
            }
            
            $vfdToken = new VfdToken();
            $vfdToken->token = $tokenData['access_token'] ?? null;
            $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
            $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
            $vfdToken->created_by = 1; // System user
            $vfdToken->created_at = now();
            $vfdToken->save();
            
            return $vfdToken->token;
        }
        
        // Check token expiry (expires_in is in SECONDS - match Yii2 calculation)
        $tokenGenAt = $vfdToken->created_at;
        $expirDuration = $vfdToken->expires_in ?? 3600;
        $dateInSec = strtotime($tokenGenAt);
        $expireDate = date('Y-m-d H:i:s', $dateInSec + $expirDuration);
        $currentDate = strtotime(date('Y-m-d H:i:s'));
        $expireDateInSec = strtotime($expireDate);
        
        if ($currentDate > $expireDateInSec) {
            // Token expired, request a new one and update EXISTING record (match Yii2)
            $tokenData = self::requestToken();
            if (!$tokenData) {
                return null;
            }
            
            // Match Yii2: Update existing token record (not create new)
            $vfdToken->token = $tokenData['access_token'] ?? null;
            $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
            $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
            $vfdToken->created_at = date('Y-m-d H:i:s'); // Match Yii2 format
            $vfdToken->created_by = 1; // System user
            $vfdToken->save();
        }
        
        return $vfdToken->token;
    }
    
    /**
     * Post Z-Report to TRA VFD API
     * 
     * @param array $vfdData VFD registration data
     * @param string $date Report date
     * @param string $time Report time
     * @param string $znumber Z-number
     * @param float $sumSalesPrevDay Daily total amount
     * @param float $cumulativeTotal Cumulative total
     * @param int $totalNoTrans Total number of transactions
     * @param float $nettamount Net amount
     * @param float $taxamount Tax amount
     * @param float $cashTotal Cash total
     * @param float $emoneyTotal E-money total
     * @return array
     */
    public static function postZReport(
        $vfdData,
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
    ) {
        // Find existing zreport using default connection
        $existingZreport = Zreport::where('znumber', $znumber)->first();
        
        if (!$existingZreport) {
            Log::warning('ZReportService::postZReport: Z-report not found, creating new record', ['znumber' => $znumber]);
        }
        
        // Prepare data for update/insert
        $zreportData = [
            'date' => $date,
            'time' => $time,
            'vrn' => $vfdData['vrn'] ?? null,
            'tin' => $vfdData['tin'] ?? null,
            'name' => $vfdData['name'] ?? null,
            'taxoffice' => $vfdData['taxoffice'] ?? null,
            'regid' => $vfdData['reg_id'] ?? null,
            'znumber' => $znumber,
            'efdserial' => $vfdData['serila'] ?? null,
            'registrationdate' => $vfdData['date'] ?? null,
            'user' => $vfdData['uin'] ?? null,
            'simimsi' => 'WEBAPI',
            'dailytotalamount' => $sumSalesPrevDay,
            'gross' => $cumulativeTotal,
            'corrections' => 0.00,
            'discounts' => 0.00,
            'surcharges' => 0.00,
            'ticketsvoid' => 0,
            'ticketsvoidtotal' => 0,
            'ticketfiscal' => $totalNoTrans,
            'ticketsnonfiscal' => 0,
            'vatrate' => 'A-18.00',
            'netamount' => $nettamount,
            'taxamount' => $taxamount,
            'cashamount' => $cashTotal,
            'emoney_amount' => $emoneyTotal,
            'emoney_type' => 'EMONEY',
            'cash_type' => 'CASH',
            'vatchangenum' => 0,
            'headchangenum' => 0,
            'city' => $vfdData['city'] ?? null,
            'mobile' => $vfdData['mobile'] ?? null,
            'address' => $vfdData['address'] ?? null,
            // Don't set ackcode/ackmsg here - will be set after successful post
        ];

        // Update existing or insert new using default connection
        if ($existingZreport) {
            $existingZreport->update($zreportData);
            $zreport = $existingZreport->fresh();
        } else {
            $zreport = Zreport::create($zreportData);
        }
        
        // Helper function to check if error is token-related
        $isTokenError = function($responseBody, $responseStatus) {
            $body = is_string($responseBody) ? $responseBody : json_encode($responseBody);
            $bodyLower = strtolower($body);
            
            return (
                stripos($bodyLower, 'invalid token') !== false ||
                stripos($bodyLower, 'token expired') !== false ||
                stripos($bodyLower, 'unauthorized') !== false ||
                stripos($bodyLower, 'bearer') !== false && stripos($bodyLower, 'invalid') !== false ||
                $responseStatus == 401
            );
        };
        
        // Helper function to make the API request
        $makeApiRequest = function($token) use ($vfdData, $date, $time, $znumber, $sumSalesPrevDay, $cumulativeTotal, $totalNoTrans, $nettamount, $taxamount, $cashTotal, $emoneyTotal, $isTokenError) {
        // Prepare request data
        $requestData = [
            'DATE' => $date,
            'TIME' => $time,
            'VRN' => $vfdData['vrn'] ?? '',
            'TIN' => strval($vfdData['tin'] ?? ''),
            'NAME' => $vfdData['name'] ?? '',
            'CITY' => $vfdData['city'] ?? '',
            'MOBILE' => $vfdData['mobile'] ?? '',
            'ADDRESS' => $vfdData['address'] ?? '',
            'TAXOFFICE' => $vfdData['taxoffice'] ?? '',
            'REGID' => $vfdData['reg_id'] ?? '',
            'ZNUMBER' => $znumber,
            'EFDSERIAL' => $vfdData['serila'] ?? '',
            'REGISTRATIONDATE' => $vfdData['date'] ?? '',
            'USER' => $vfdData['uin'] ?? '',
            'SIMIMSI' => 'WEBAPI',
            'DAILYTOTALAMOUNT' => $sumSalesPrevDay,
            'GROSS' => $cumulativeTotal,
            'CORRECTIONS' => 0.00,
            'DISCOUNTS' => 0.00,
            'SURCHARGES' => 0.00,
            'TICKETSVOID' => 0,
            'TICKETSVOIDTOTAL' => 0.00,
            'TICKETSFISCAL' => $totalNoTrans,
            'TICKETSNONFISCAL' => 0,
            'VATRATE' => 'A-18.00',
            'NETAMOUNT' => $nettamount,
            'TAXAMOUNT' => $taxamount,
            'CASH_AMOUNT' => $cashTotal,
            'EMONEY_AMOUNT' => $emoneyTotal,
            'EMONEY_TYPE' => 'EMONEY',
            'CASH_TYPE' => 'CASH',
            'VATCHANGENUM' => 0,
            'HEADCHANGENUM' => 0,
            'FWVERSION' => 3.0,
            'FWCHECKSUM' => 'WEBAPI',
            'token' => $token
        ];
        
        // Post to VFD API
        $vfdApiUrl = config('params.api_urls.vfd.default') ?: config('params.api_urls.vfd.production');
        
        if (empty($vfdApiUrl)) {
            Log::error('VFD API URL not configured');
            return [
                'status' => 0,
                    'message' => 'VFD API URL not configured',
                    'retry' => false
            ];
        }
        
        try {
            $response = Http::timeout(60)
                ->post($vfdApiUrl . 'receipt/post-report', $requestData);
            
                $responseStatus = $response->status();
                $responseBody = $response->body();
                $responseData = $response->json();
                
                // Check if this is a token error
                if ($isTokenError($responseBody, $responseStatus)) {
                    Log::warning('ZReportService::postZReport: Invalid token error detected', [
                        'status' => $responseStatus,
                        'znumber' => $znumber
                    ]);
                    return [
                        'status' => 0,
                        'message' => 'Invalid token - needs refresh',
                        'data' => $responseData ?? ['body' => $responseBody],
                        'retry' => true,
                        'is_token_error' => true
                    ];
                }
                
                if ($response->successful()) {
                // Check if response has ZACK
                if (isset($responseData['ZACK'])) {
                    $zack = $responseData['ZACK'];
                    
                    if (isset($zack['ACKCODE']) && $zack['ACKCODE'] != null) {
                            // Truncate ackmsg to fit database columns
                            // vfd_zreport.ackmsg is VARCHAR(25), zreport.ackmsg is VARCHAR(50)
                            $ackmsgFull = $zack['ACKMSG'] ?? null;
                            $vfdZreportMaxLength = 25; // vfd_zreport table column size
                            $zreportMaxLength = 50; // zreport table column size
                            
                            // Truncate for vfd_zreport (25 chars)
                            $vfdAckmsg = $ackmsgFull;
                            if ($vfdAckmsg && strlen($vfdAckmsg) > $vfdZreportMaxLength) {
                                Log::warning('ZReportService::postZReport: ackmsg too long for vfd_zreport, truncating', [
                                    'znumber' => $znumber,
                                    'original_length' => strlen($vfdAckmsg),
                                    'truncated_length' => $vfdZreportMaxLength,
                                    'full_message' => $vfdAckmsg
                                ]);
                                $vfdAckmsg = substr($vfdAckmsg, 0, $vfdZreportMaxLength);
                            }
                            
                            // Truncate for zreport (50 chars)
                            $zreportAckmsg = $ackmsgFull;
                            if ($zreportAckmsg && strlen($zreportAckmsg) > $zreportMaxLength) {
                                $zreportAckmsg = substr($zreportAckmsg, 0, $zreportMaxLength);
                            }
                            
                        // Success - save to vfd_zreport
                        $vfdZreport = new VfdZreport();
                        $vfdZreport->znumber = $zack['ZNUMBER'] ?? $znumber;
                        $vfdZreport->received_date = $zack['DATE'] ?? $date;
                        $vfdZreport->received_time = $zack['TIME'] ?? $time;
                        $vfdZreport->ackcode = $zack['ACKCODE'];
                            $vfdZreport->ackmsg = $vfdAckmsg; // Truncated to 25 chars
                        $vfdZreport->created_at = now();
                            
                            try {
                        $vfdZreport->save();
                            } catch (\Exception $saveEx) {
                                // If still fails, log error and continue
                                Log::error('ZReportService::postZReport: Failed to save vfd_zreport', [
                                    'znumber' => $znumber,
                                    'error' => $saveEx->getMessage(),
                                    'ackcode' => $zack['ACKCODE'],
                                    'ackmsg_length' => strlen($vfdAckmsg),
                                    'full_ackmsg' => $ackmsgFull
                                ]);
                                // Continue anyway to update zreport
                            }
                        
                            // Update zreport with ackcode and ackmsg using default connection
                            Zreport::where('znumber', $znumber)->update([
                                'ackcode' => $zack['ACKCODE'],
                                'ackmsg' => $zreportAckmsg // Truncated to 50 chars
                            ]);
                            
                            // Log full message if truncated
                            if ($ackmsgFull && strlen($ackmsgFull) > 25) {
                                Log::info('ZReportService::postZReport: Full ackmsg', [
                                    'znumber' => $znumber,
                                    'ackcode' => $zack['ACKCODE'],
                                    'full_ackmsg' => $ackmsgFull
                                ]);
                            }
                        
                        return [
                            'status' => 1,
                            'message' => 'Successfully sent and logged',
                                'data' => $vfdZreport->toArray(),
                                'retry' => false
                        ];
                    }
                }
                
                return [
                    'status' => 0,
                    'message' => 'Failed to send Z-Report',
                        'data' => $responseData,
                        'retry' => false
                ];
            }
            
            return [
                'status' => 0,
                'message' => 'HTTP request failed',
                'data' => [
                        'status' => $responseStatus,
                        'body' => $responseBody
                    ],
                    'retry' => false
            ];
            } catch (\Exception $e) {
                Log::error('Exception in makeApiRequest', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 0,
                    'message' => 'Exception: ' . $e->getMessage(),
                    'retry' => false
                ];
            }
        };
        
        // Get token and make first attempt
        $token = self::getToken();
        if (!$token) {
            return [
                'status' => 0,
                'message' => 'Failed to get VFD token'
            ];
        }
        
        $result = $makeApiRequest($token);
        
        // If token error detected, refresh token and retry once
        if (isset($result['retry']) && $result['retry'] === true && isset($result['is_token_error']) && $result['is_token_error'] === true) {
            Log::info('ZReportService::postZReport: Token error detected, refreshing and retrying', ['znumber' => $znumber]);
            
            $vfdToken = VfdToken::orderBy('created_at', 'desc')->first();
            if ($vfdToken) {
                $tokenData = self::requestToken();
                if ($tokenData) {
                    $vfdToken->token = $tokenData['access_token'] ?? null;
                    $vfdToken->token_type = $tokenData['token_type'] ?? 'Bearer';
                    $vfdToken->expires_in = $tokenData['expires_in'] ?? 3600;
                    $vfdToken->created_at = date('Y-m-d H:i:s');
                    $vfdToken->created_by = 1;
                    $vfdToken->save();
                    
                    // Retry with new token
                    return $makeApiRequest($vfdToken->token);
                } else {
                    Log::error('ZReportService::postZReport: Failed to refresh token', ['znumber' => $znumber]);
                    return ['status' => 0, 'message' => 'Invalid token and failed to refresh token'];
                }
            } else {
                Log::error('ZReportService::postZReport: No token found to refresh', ['znumber' => $znumber]);
                return ['status' => 0, 'message' => 'Invalid token and no token record found to refresh'];
        }
        }
        
        return $result;
    }
}

