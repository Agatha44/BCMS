<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReconciliationController extends BasicController
{
    /**
     * Run reconciliation process
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function runReconciliation(Request $request): JsonResponse
    {
        try {
            // Run reconciliation for all tables
            $queries = [
                "UPDATE bcmis.incident_fine SET bill_gen_at = DATE_SUB(trx_dt_tm, INTERVAL 20 SECOND) WHERE trx_dt_tm < bill_gen_at",
                "UPDATE bcmis.overload_fine SET bill_gen_at = DATE_SUB(trx_dt_tm, INTERVAL 20 SECOND) WHERE trx_dt_tm < bill_gen_at",
            ];

            $results = [];
            $totalUpdated = 0;

            foreach ($queries as $query) {
                try {
                    // Extract table name from query
                    preg_match('/UPDATE bcmis\.(\w+)/', $query, $matches);
                    $table = $matches[1] ?? 'unknown';

                    // Execute the update query
                    DB::statement($query);

                    // Get affected rows count using PDO
                    $count = DB::getPdo()->query("SELECT ROW_COUNT()")->fetchColumn();

                    $results[] = [
                        'table' => $table,
                        'records_updated' => (int)$count,
                    ];

                    $totalUpdated += (int)$count;
                } catch (\Exception $e) {
                    preg_match('/UPDATE bcmis\.(\w+)/', $query, $matches);
                    $table = $matches[1] ?? 'unknown';

                    Log::error('Reconciliation error for ' . $table, [
                        'error' => $e->getMessage(),
                    ]);

                    $results[] = [
                        'table' => $table,
                        'error' => $e->getMessage(),
                        'records_updated' => 0,
                    ];
                }
            }

            return $this->sendResponse([
                'total_records_updated' => $totalUpdated,
                'results' => $results,
            ], 'Reconciliation completed successfully!');

        } catch (\Exception $e) {
            Log::error('Reconciliation API error', [
                'error' => $e->getMessage(),
            ]);

            return $this->sendError('Failed to run reconciliation: ' . $e->getMessage(), [], 0, 500);
        }
    }

    /**
     * Post reconciliation request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function post(Request $request): JsonResponse
    {
        try {
            $recon_date = $request->all();
            $sendReq = $this->sendRequest($recon_date);

            if ($sendReq === false) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Failed!, Please Try Again'
                ]);
            }

            $gepgRes = json_decode($sendReq, true);

            // If the response is already JSON, use it directly
            if (json_last_error() === JSON_ERROR_NONE && is_array($gepgRes)) {
                $gepg_response_array = $gepgRes;
            } else {
                // If it's XML, parse it
                $decoded_data = simplexml_load_string($sendReq);
                
                if ($decoded_data === false) {
                    Log::error('Failed to parse GePG XML response', [
                        'raw_response' => $sendReq
                    ]);
                    return response()->json([
                        'status' => 1,
                        'message' => 'Failed!, Please Try Again'
                    ]);
                }

                $gepg_response_json = json_encode($decoded_data);
                $gepg_response_array = json_decode($gepg_response_json, true);
            }

            $gepgAck = $gepg_response_array['gepgSpReconcReqAck']['ReconcStsCode'] ?? null;

            if ($gepgAck == 7101) {
                return response()->json([
                    'status' => 7101,
                    'message' => 'Reconciliation Request Succesfully Sent'
                ]);
            } elseif ($gepgAck == 7242) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Failed!, Please Try Again'
                ]);
            } elseif ($gepgAck == 7201) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Failed!, Please Try Again'
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'message' => 'Failed!, Please Try Again'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Reconciliation post error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 1,
                'message' => 'Failed!, Please Try Again'
            ]);
        }
    }

    /**
     * Send reconciliation request to external API
     *
     * @param array $recon_date
     * @return string|false
     */
    private function sendRequest(array $recon_date)
    {
        try {
            $response = Http::withHeaders([
                'content-type' => 'application/json',
            ])->post('http://10.10.47.33:93/recon/send', [
                'recon_date' => $recon_date
            ]);

            if ($response->successful()) {
                return $response->body();
            }

            Log::error('Reconciliation API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Reconciliation sendRequest error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

}

