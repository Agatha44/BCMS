<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Firebase Helper Functions
 */
class FirebaseHelper
{
    /**
     * Base64 URL encode (RFC 4648)
     * 
     * @param string $data
     * @return string
     */
    public static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get OAuth 2.0 access token for Firebase using service account
     * 
     * @return string|null
     */
    public static function getAccessToken(): ?string
    {
        try {
            $firebaseConfig = config('params.firebase');
            $serviceAccountPath = $firebaseConfig['service_account_path'] ?? null;

            // Resolve relative paths to absolute paths
            if (!empty($serviceAccountPath) && !str_starts_with($serviceAccountPath, '/')) {
                // If it's a relative path, resolve it relative to Laravel's base path
                $serviceAccountPath = base_path($serviceAccountPath);
            }

            // Log the path being checked for debugging
            Log::info('Checking Firebase service account file', [
                'path' => $serviceAccountPath,
                'file_exists' => file_exists($serviceAccountPath),
                'is_readable' => file_exists($serviceAccountPath) ? is_readable($serviceAccountPath) : false,
            ]);

            if (empty($serviceAccountPath) || !file_exists($serviceAccountPath)) {
                Log::error('Firebase service account file not found', [
                    'path' => $serviceAccountPath,
                    'resolved_path' => $serviceAccountPath,
                    'base_path' => base_path(),
                    'storage_path' => storage_path('app'),
                ]);
                return null;
            }

            if (!is_readable($serviceAccountPath)) {
                Log::error('Firebase service account file is not readable', [
                    'path' => $serviceAccountPath,
                    'permissions' => substr(sprintf('%o', fileperms($serviceAccountPath)), -4),
                ]);
                return null;
            }

            $fileContents = file_get_contents($serviceAccountPath);
            if ($fileContents === false) {
                Log::error('Failed to read Firebase service account file', [
                    'path' => $serviceAccountPath,
                    'error' => error_get_last(),
                ]);
                return null;
            }

            $serviceAccount = json_decode($fileContents, true);
            
            if (!$serviceAccount) {
                $jsonError = json_last_error_msg();
                Log::error('Failed to parse Firebase service account JSON', [
                    'path' => $serviceAccountPath,
                    'json_error' => $jsonError,
                    'file_size' => strlen($fileContents),
                ]);
                return null;
            }

            // Validate required fields
            $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
            foreach ($requiredFields as $field) {
                if (!isset($serviceAccount[$field])) {
                    Log::error('Firebase service account JSON missing required field', [
                        'missing_field' => $field,
                        'path' => $serviceAccountPath,
                    ]);
                    return null;
                }
            }

            // Create JWT for OAuth 2.0
            $now = time();
            $jwt = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];

            // Sign JWT with private key
            $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = self::base64UrlEncode(json_encode($jwt));
            
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            if (!$privateKey) {
                $opensslError = openssl_error_string();
                Log::error('Failed to load Firebase private key', [
                    'openssl_error' => $opensslError,
                    'client_email' => $serviceAccount['client_email'] ?? 'unknown',
                ]);
                return null;
            }

            openssl_sign($header . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            openssl_free_key($privateKey);
            
            $jwtToken = $header . '.' . $payload . '.' . self::base64UrlEncode($signature);

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwtToken,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                return $tokenData['access_token'] ?? null;
            }

            Log::error('Failed to get Firebase access token', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error getting Firebase access token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

