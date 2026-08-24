<?php

namespace App\Traits;

use App\Helpers\DBHelper;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait FMSTrait
{
    /**
     * Get access token from FMS API
     *
     * @param array $tokenPayload
     * @param string $tokenUrl
     * @return array|string
     */
    public function getAccessToken(array $tokenPayload, string $tokenUrl): array|string
    {
        try {
            $response = Http::withoutVerifying() // Disable SSL verification for development/testing
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($tokenUrl, $tokenPayload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('FMS API access token retrieved successfully', [
                    'url' => $tokenUrl,
                ]);
                return $responseData;
            }

            Log::error('Failed to get FMS API access token', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $tokenUrl,
            ]);

            return [
                'error' => 'Failed to get access token',
                'status' => $response->status(),
                'message' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('Exception while getting FMS API access token', [
                'error' => $e->getMessage(),
                'url' => $tokenUrl,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Authenticate with FMS API and get access token
     *
     * @param string $passphrase
     * @param int $sourceSystemId
     * @return array|string
     */
    public function authenticateFMS(string $passphrase = 'mdc146$p455wd', int $sourceSystemId = 141): array|string
    {
        $tokenUrl = DBHelper::getFMSApi() . '/authenticate-system';
        $tokenPayload = [
            'systemId' => $sourceSystemId,
            'passphrase' => $passphrase
        ];

        return $this->getAccessToken($tokenPayload, $tokenUrl);
    }

    /**
     * Submit payment request to FMS API
     *
     * @param array $paymentData
     * @param string|null $accessToken
     * @return array|string
     */
    public function submitPaymentRequest(array $paymentData, ?string $accessToken = null): array|string
    {
        try {
            $postUrl = DBHelper::getFMSApi() . '/submit-payment-request';

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // Add authorization header if access token is provided
            if ($accessToken) {
                $headers['Authorization'] = 'Bearer ' . $accessToken;
            }

            $response = Http::withoutVerifying() // Disable SSL verification for development/testing
                ->withHeaders($headers)
                ->post($postUrl, $paymentData);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info('Payment request submitted to FMS API successfully', [
                    'url' => $postUrl,
                ]);
                return $responseData;
            }

            Log::error('Failed to submit payment request to FMS API', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $postUrl,
            ]);

            return [
                'error' => 'Failed to submit payment request',
                'status' => $response->status(),
                'message' => $response->body(),
            ];
        } catch (Exception $e) {
            Log::error('Exception while submitting payment request to FMS API', [
                'error' => $e->getMessage(),
                'url' => $postUrl ?? 'N/A',
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Complete flow: Authenticate and submit payment request
     *
     * @param array $paymentData
     * @param string $passphrase
     * @param int $sourceSystemId
     * @return array|string
     */
    public function authenticateAndSubmitPayment(array $paymentData, string $passphrase = 'mdc146$p455wd', int $sourceSystemId = 141): array|string
    {
        // Step 1: Authenticate and get access token
        $tokenResponse = $this->authenticateFMS($passphrase, $sourceSystemId);

        // Check if authentication was successful
        if (isset($tokenResponse['error']) || !isset($tokenResponse['access_token'])) {
            Log::error('FMS authentication failed', [
                'response' => $tokenResponse
            ]);
            return $tokenResponse;
        }

        $accessToken = $tokenResponse['access_token'];

        // Step 2: Submit payment request with access token
        return $this->submitPaymentRequest($paymentData, $accessToken);
    }
}

