<?php

namespace App\Models;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EOffice
{
    /**
     * Get OAuth access token from eOffice
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $url
     * @return array|string
     */
    public static function getAccessToken($clientId, $clientSecret, $url): array|string
    {
        try {
            $response = Http::withoutVerifying() // Disable SSL verification for development/testing
                ->asForm()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                ])
                ->post($url, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get eOffice access token', [
                'status' => $response->status(),
                'body' => $response->body(),
                'url' => $url,
            ]);

            return [
                'error' => 'Failed to get access token',
                'status' => $response->status(),
                'message' => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('Exception while getting eOffice access token', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);

            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}

