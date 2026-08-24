<?php

namespace App\Services\Erms;

use App\Traits\Erms\ErmsAccessTokenTrait;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErmsPayableSubmissionService
{
    use ErmsAccessTokenTrait;

    /**
     * Preview signing for the inner ERMS payable `data` object (unsigned), same shape as {@see submit()}.
     *
     * @param  array<string, mixed>  $unsignedData
     * @return array{endpoint: string, headers: array<string, string>, unsigned_data: array<string, mixed>, signed_payload: array{data: array<string, mixed>, signature: string}}
     */
    public function previewPayablePayload(array $unsignedData): array
    {
        /** @var ErmsPayloadSigner $signer */
        $signer = app(ErmsPayloadSigner::class);

        return [
            'endpoint' => $this->payableEndpoint(),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'unsigned_data' => $unsignedData,
            'signed_payload' => $signer->signDataEnvelope($unsignedData),
        ];
    }

    /**
     * Submit a signed payable request to ERMS.
     *
     * @param  array<string, mixed>  $unsignedData  Inner `data` body (from e.g. {@see OvertimeBatchPayableMapper::map()} or {@see PayrollRunPayableMapper::map()}).
     * @param  array<string, mixed>  $logContext      Merged into completion / failure logs (e.g. batch_id, payroll_run_id).
     *
     * @return array{
     *   ok: bool,
     *   http_status: int|null,
     *   endpoint: string,
     *   request: array<string, mixed>|null,
     *   response: mixed,
     *   error: string|null
     * }
     */
    public function submit(array $unsignedData, array $logContext = []): array
    {
        try {
            /** @var ErmsPayloadSigner $signer */
            $signer = app(ErmsPayloadSigner::class);
            $signedPayload = $signer->signDataEnvelope($unsignedData);

            $endpoint = $this->payableEndpoint();
            if ($endpoint === '') {
                throw new \RuntimeException('ERMS payable endpoint is empty. Configure erms.urls.create_payable_payment_request.');
            }

            $token = $this->getErmsAccessToken();
            $response = Http::timeout(60)
                ->withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $signedPayload);

            $body = $response->json() ?? $response->body();
            $ok = $response->successful();

            Log::info('ERMS payable submission completed', array_merge([
                'ok' => $ok,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
            ], $logContext));

            return [
                'ok' => $ok,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'request' => $signedPayload,
                'response' => $body,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('ERMS payable submission failed', array_merge([
                'error' => $e->getMessage(),
            ], $logContext));

            return [
                'ok' => false,
                'http_status' => null,
                'endpoint' => $this->payableEndpoint(),
                'request' => null,
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function payableEndpoint(): string
    {
        return (string) config(
            'erms.urls.create_payable_payment_request',
            Config::get('services.erms.create_payable_payment_request_url', Config::get('services.erms.create_payable_payment_request_path', ''))
        );
    }
}

