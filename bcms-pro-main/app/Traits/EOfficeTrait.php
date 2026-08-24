<?php

namespace App\Traits;

use App\Helpers\DBHelper;
use App\Models\EOffice;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait EOfficeTrait
{
    public static function requestStatus($access_token, $url): array
    {
        try {
            $response = Http::withoutVerifying() // Disable SSL verification for development/testing
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ])->get($url);

            return $response->json();
        } catch (Exception $ex) {
            return ['error' => $ex->getMessage()];
        }
    }
    public static function postRequest($data, $url)
    {
        $response = Http::withoutVerifying() // Disable SSL verification for development/testing
            ->post($url, $data, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);
        if ($response->failed()) {
            return $response->body();
        }

        return $response->json();
    }
    public static function getRequest($url)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for development/testing
            CURLOPT_SSL_VERIFYHOST => false, // Disable SSL verification for development/testing
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        if (isset($error)) {
            return $error;
        }
        return json_decode($response, true);
    }

    public function requestToken()
    {
        try {
            $clientId = 'eoffice';
            $clientSecret = 'eoffice';
            // Use DBHelper from EOfficeHelper to determine correct eOffice base URL
            $url = DBHelper::getEOfficeLink() . 'oauth/token';
            // $url = 'https://demo-eoffice.nssf.go.tz:8082/oauth/token';
            return EOffice::getAccessToken($clientId, $clientSecret, $url);

        } catch (Exception $ex) {
            $err = $ex->getMessage();
            return $this->sendError('Error, please try again later', ['error' => $err], 0, 500);
        }
    }

    public function postEofficeOffice($accessToken, $url, $processName, $referenceId, $requestedAmount, $userDetails, $attachments, $action, $responseUrl = null)
    {
        // Log::info('ndani');
        // Log::info($userDetails);
        // Log::info($userDetails->get('email'));

        // Remove special characters from referenceId (keep only alphanumeric characters)
        // Example: "OTB-20260203-0001" becomes "OTB202602030001"
        $sanitizedReferenceId = preg_replace('/[^a-zA-Z0-9]/', '', $referenceId);

        $data = [
            "systemName" => "BMS",
            "processName" => $processName,
            "referenceId" => $sanitizedReferenceId,
            "documentTitle" => $action == 'APPROVAL' ? 'OVERTIME PAYMENT APPROVAL REQUEST '. $sanitizedReferenceId : $processName,
            "createdBy" =>    $userDetails['email'],
            "createdByName" => $userDetails['fullname'],
            "documentReference" => $sanitizedReferenceId,
            "requestType" => "BMS",
            "requestAction" => $action,
            "institutionCode" => 2010700,
            // Build a full callback URL using BMS API base URL from DBHelper (EOfficeHelper)
            "responseUrl" => $responseUrl,
            "approvalSequence" => [],
            "requestedAmount" => $requestedAmount,
            "attachments" => $attachments
        ];

        Log::info('Data', $data);

        try {
            // Log::info('payload data '.json_encode($data));
            // Log::info('auth data '.json_encode($accessToken));
            // Log::info('auth data '.json_encode($url));
            $response = Http::withoutVerifying() // Disable SSL verification for development/testing
                ->withToken($accessToken)
                ->post($url, $data);
            $tokenResponse = $response->json();

            Log::info('tokenResponse '.json_encode($tokenResponse));


            if ($tokenResponse['code'] == '9000' || $tokenResponse['code'] == '9004') {
                // Log::info('response '.json_encode($response));

                $response = [
                    'status' => true,
                    'data' => null,
                    'message' => $tokenResponse['message'],
                ];
                return response()->json($response, 200);
            }
            $response = [
                'status'=>false,
                'status_code'=> 0,
                'message' => $tokenResponse['message']
            ];
            return response()->json($response, 200);

        } catch (Exception $err) {
            // Log::info($err->getMessage());
            $response = [
                'status'=>false,
                'status_code'=>0,
                'message' => $err->getMessage()
            ];
            return response()->json($response, 200);
        }
    }

}
