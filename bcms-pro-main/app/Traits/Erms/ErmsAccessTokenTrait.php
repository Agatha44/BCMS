<?php

namespace App\Traits\Erms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

trait ErmsAccessTokenTrait
{
    /**
     * OAuth2 client_credentials token for ERMS, cached until shortly before {@see expires_in}.
     */
    protected function getErmsAccessToken(): string
    {
        $url = (string) config('erms.urls.auth_token');
        $clientId = config('erms.client_id');
        $clientSecret = config('erms.client_secret');

        if ($url === '' || $clientId === null || $clientId === '' || $clientSecret === null || $clientSecret === '') {
            throw new RuntimeException('ERMS OAuth is not configured (ERMS_BASE_URL, ERMS_CLIENT_ID, ERMS_CLIENT_SECRET).');
        }

        $cacheKey = $this->ermsAccessTokenCacheKey();

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::timeout(30)
            ->asForm()
            ->withBasicAuth((string) $clientId, (string) $clientSecret)
            ->post($url, [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'ERMS token request failed: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $token = isset($body['access_token']) && is_string($body['access_token']) ? $body['access_token'] : null;
        if ($token === null || $token === '') {
            throw new RuntimeException('ERMS token response missing access_token.');
        }

        $expiresIn = isset($body['expires_in']) ? (int) $body['expires_in'] : 60;
        $bufferSeconds = 60;
        $ttlSeconds = max(1, $expiresIn - $bufferSeconds);

        Cache::put($cacheKey, $token, now()->addSeconds($ttlSeconds));

        return $token;
    }

    /**
     * Cache key scoped by client id so credential rotation does not reuse an old token.
     */
    protected function ermsAccessTokenCacheKey(): string
    {
        $clientId = (string) config('erms.client_id', '');

        return 'erms:oauth:access_token:'.$clientId;
    }
}
