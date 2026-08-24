<?php

namespace App\Services\Erms;

use RuntimeException;
/**
 * Signs ERMS request bodies: envelope { "data": {...}, "signature": "<base64 RSA>" }.
 * Signing uses the client PKCS#12 private key (RSA 2048) and SHA-256.
 *
 * The signed octets are the UTF-8 JSON of `data` after recursive key sorting (canonical object order),
 * with JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE. If ERMS verifies a different serialization,
 * adjust {@see stringToSign()} only.
 */
class ErmsPayloadSigner
{
    /**
     * @param  array<string, mixed>  $data  Payload body (becomes the "data" property).
     * @return array{data: array<string, mixed>, signature: string}
     */
    public function signDataEnvelope(array $data): array
    {
        $signature = $this->signString($this->stringToSign($data));

        return [
            'data' => $data,
            'signature' => $signature,
        ];
    }

    /** UTF-8 message bytes (typically canonical JSON) signed with RSA-SHA256. */
    public function signString(string $payload): string
    {
        $key = $this->loadPrivateKey();

        $signature = '';
        if (! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('ERMS payload signing failed (openssl_sign).');
        }

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function stringToSign(array $data): string
    {
        $message = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($message === false) {
            throw new RuntimeException('Failed to encode ERMS payload as JSON.');
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $envelope  Must contain key "data" (and optionally "signature", ignored).
     * @return array{data: array<string, mixed>, signature: string}
     */
    public function signEnvelope(array $envelope): array
    {
        if (! array_key_exists('data', $envelope)) {
            throw new RuntimeException('ERMS envelope must contain a "data" key.');
        }

        $data = $envelope['data'];
        if (! is_array($data)) {
            throw new RuntimeException('ERMS envelope "data" must be an array.');
        }

        return $this->signDataEnvelope($data);
    }

    private function loadPrivateKey()
    {
        $pemPath = $this->resolvePemPath();
        if ($pemPath !== null) {
            $contents = file_get_contents($pemPath);
            if ($contents === false) {
                throw new RuntimeException("Cannot read private key file: {$pemPath}");
            }

            $pemPassword = config('erms.private_pem_password');
            if ($pemPassword === null || $pemPassword === '') {
                $pemPassword = env('ERMS_PRIVATE_PEM_PASSWORD', null);
            }

            self::drainOpenSslErrors();
            $privateKey = openssl_pkey_get_private($contents, $pemPassword);
            if ($privateKey === false) {
                $detail = self::collectOpenSslErrors();
                throw new RuntimeException(
                    'Failed to load private key. Ensure PEM password is correct if protected.'
                    .($detail !== '' ? ' OpenSSL: '.$detail : '')
                );
            }

            return $privateKey;
        }

        $pfxPath = $this->resolvePfxPath();
        $pfxPassword = (string) config('erms.private_pfx_password', '');
        if ($pfxPath !== null) {
            $contents = file_get_contents($pfxPath);
            if ($contents === false) {
                throw new RuntimeException('Could not read ERMS private PFX file.');
            }

            self::drainOpenSslErrors();

            $certs = [];
            if (! openssl_pkcs12_read($contents, $certs, $pfxPassword)) {
                $detail = self::collectOpenSslErrors();
                $hint = ' Usually: wrong ERMS_PRIVATE_PFX_PASSWORD, corrupt .pfx, or not a PKCS#12 file.';
                throw new RuntimeException(
                    'Could not read ERMS PKCS#12.'.$hint
                    .($detail !== '' ? ' OpenSSL: '.$detail : '')
                );
            }

            $privateKey = openssl_pkey_get_private($certs['pkey'] ?? '');
            if ($privateKey === false) {
                throw new RuntimeException('Could not parse private key from ERMS PKCS#12.');
            }

            return $privateKey;
        }

        throw new RuntimeException('ERMS private key is not configured/readable. Checked PEM then PFX paths.');
    }

    private function resolvePemPath(): ?string
    {
        $configuredPath = (string) config('erms.paths.private_pem', '');
        if ($configuredPath !== '' && is_readable($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            storage_path('keys/erms_clientprivate.pem'),
            base_path('storage/keys/erms_clientprivate.pem'),
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function resolvePfxPath(): ?string
    {
        $configuredPath = (string) config('erms.paths.private_pfx', '');
        if ($configuredPath !== '' && is_readable($configuredPath)) {
            return $configuredPath;
        }

        $candidates = [
            storage_path('keys/erms_clientprivate.pfx'),
            storage_path('app/keys/erms_clientprivate.pfx'),
            base_path('storage/keys/erms_clientprivate.pfx'),
        ];

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function drainOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
        }
    }

    private static function collectOpenSslErrors(): string
    {
        $messages = [];
        while (($msg = openssl_error_string()) !== false) {
            $messages[] = $msg;
        }

        return implode(' | ', $messages);
    }

}
