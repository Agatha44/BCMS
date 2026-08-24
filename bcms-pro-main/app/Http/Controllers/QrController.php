<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class QrController extends BasicController
{
    /**
     * Encryption key - must match the one in Flutter app
     * IMPORTANT: Must be exactly 32 characters for AES-256
     */
    private const ENCRYPTION_KEY = 'NSSF_BRIDGE_QR_KEY_2025_SECURE01';

    /**
     * Decrypt QR code data
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function decryptQrData(Request $request): JsonResponse
    {
        try {
            // Validate request
            $request->validate([
                'encrypted_data' => 'required|string',
            ]);

            $encryptedData = $request->input('encrypted_data');

            if (empty($encryptedData)) {
                return $this->sendError('Encrypted data is required', [], 400, 400);
            }

            // Trim whitespace and remove any URL encoding
            $encryptedData = trim($encryptedData);
            
            // Replace URL-safe base64 characters if present
            $encryptedData = str_replace(['-', '_'], ['+', '/'], $encryptedData);
            
            // Add padding if needed (base64 padding)
            $padding = strlen($encryptedData) % 4;
            if ($padding > 0) {
                $encryptedData .= str_repeat('=', 4 - $padding);
            }

            Log::info('QR Decryption Request', [
                'encrypted_data_length' => strlen($encryptedData),
                'encrypted_data_preview' => substr($encryptedData, 0, 50) . '...'
            ]);

            // Decrypt the data
            $decryptedData = $this->decrypt($encryptedData);

            // Parse JSON
            $jsonData = json_decode($decryptedData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('QR Decryption: Invalid JSON after decryption', [
                    'decrypted_data' => $decryptedData,
                    'json_error' => json_last_error_msg()
                ]);
                return $this->sendError('Decrypted data is not valid JSON', [
                    'decrypted_data' => $decryptedData
                ], 422, 422);
            }

            Log::info('[QR Decrypt] Account resolved for POS payment', [
                'account_no' => $jsonData['account_no'] ?? 'N/A',
                'platform' => $jsonData['platform'] ?? 'N/A',
                'device_model' => $jsonData['device_model'] ?? 'N/A',
            ]);

            return $this->sendResponse($jsonData, 'QR code decrypted successfully');

        } catch (\Exception $e) {
            Log::error('QR Decryption Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError(
                'Failed to decrypt QR code data: ' . $e->getMessage(),
                [],
                500,
                500
            );
        }
    }

    /**
     * Decrypt AES-256-CBC encrypted data
     * 
     * @param string $encryptedData Base64 encoded encrypted data (IV + ciphertext)
     * @return string Decrypted plain text
     * @throws \Exception
     */
    private function decrypt(string $encryptedData): string
    {
        // Decode base64
        $combined = base64_decode($encryptedData, true);

        if ($combined === false) {
            throw new \Exception('Invalid base64 encoded data');
        }

        // Check minimum length (16 bytes IV + at least 16 bytes ciphertext)
        if (strlen($combined) < 32) {
            throw new \Exception('Encrypted data too short. Minimum 32 bytes required (16 IV + 16 ciphertext). Got: ' . strlen($combined));
        }

        // Extract IV (first 16 bytes) and encrypted data (rest)
        $iv = substr($combined, 0, 16);
        $encryptedBytes = substr($combined, 16);

        // Create key from encryption key string
        // In Dart: final keyBytes = utf8.encode(keyString); final key = encrypt.Key(keyBytes);
        // In PHP, for ASCII strings, the bytes are the same, but let's ensure we're using it correctly
        $keyString = self::ENCRYPTION_KEY;
        
        // Validate key length
        if (strlen($keyString) !== 32) {
            throw new \Exception('Encryption key must be exactly 32 characters. Current length: ' . strlen($keyString));
        }
        
        // Convert to UTF-8 bytes explicitly (though for ASCII it's the same)
        // This ensures compatibility with Dart's utf8.encode()
        $key = mb_convert_encoding($keyString, 'UTF-8', 'UTF-8');
        
        // Ensure it's exactly 32 bytes
        if (strlen($key) !== 32) {
            throw new \Exception('Key must be exactly 32 bytes after encoding. Got: ' . strlen($key));
        }

        // Log debug info
            Log::debug('Decryption Debug', [
                'combined_length' => strlen($combined),
                'iv_length' => strlen($iv),
                'iv_hex' => bin2hex($iv),
                'encrypted_bytes_length' => strlen($encryptedBytes),
                'encrypted_bytes_hex_preview' => bin2hex(substr($encryptedBytes, 0, 32)),
                'key_length' => strlen($key),
                'key' => $key // For debugging - remove in production
            ]);

        // Clear OpenSSL errors
        while (openssl_error_string() !== false) {
            // Clear all errors
        }

        // Decrypt using AES-256-CBC with PKCS7 padding (default for OpenSSL)
        // OPENSSL_RAW_DATA means we're working with raw binary data, not base64
        // Note: The Dart encrypt package uses the key directly as UTF-8 bytes, which for ASCII is the same
        $decrypted = openssl_decrypt(
            $encryptedBytes,
            'AES-256-CBC',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            $errors = [];
            while (($error = openssl_error_string()) !== false) {
                $errors[] = $error;
            }
            $errorMessage = !empty($errors) ? implode('; ', $errors) : 'Unknown OpenSSL error';
            
            // Additional debugging: check if ciphertext length is multiple of 16 (required for AES)
            $encryptedLength = strlen($encryptedBytes);
            $isMultipleOf16 = ($encryptedLength % 16 === 0);
            
            Log::error('OpenSSL Decryption Failed', [
                'errors' => $errors,
                'iv_hex' => bin2hex($iv),
                'iv_length' => strlen($iv),
                'encrypted_length' => $encryptedLength,
                'is_multiple_of_16' => $isMultipleOf16,
                'encrypted_preview_hex' => bin2hex(substr($encryptedBytes, 0, min(64, $encryptedLength))),
                'key_length' => strlen($key),
                'key' => $key // Log key for debugging (remove in production)
            ]);
            
            // If not multiple of 16, that's likely the issue
            if (!$isMultipleOf16) {
                throw new \Exception('Decryption failed: Encrypted data length (' . $encryptedLength . ') is not a multiple of 16. This suggests corrupted data or incorrect extraction. ' . $errorMessage);
            }
            
            throw new \Exception('Decryption failed: ' . $errorMessage);
        }

        // PKCS7 padding is automatically removed by openssl_decrypt
        return $decrypted;
    }

    /**
     * Health check endpoint for QR decryption service
     * 
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        return $this->sendResponse([
            'service' => 'QR Decryption Service',
            'status' => 'operational',
            'encryption_method' => 'AES-256-CBC',
            'key_length' => strlen(self::ENCRYPTION_KEY)
        ], 'QR decryption service is operational');
    }
}

