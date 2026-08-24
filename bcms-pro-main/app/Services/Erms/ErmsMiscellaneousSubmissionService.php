<?php

namespace App\Services\Erms;

use App\Traits\Erms\ErmsAccessTokenTrait;
use App\Traits\Erms\ErmsMiscellaneousPayloadTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErmsMiscellaneousSubmissionService
{
    use ErmsAccessTokenTrait;

    /**
     * Submit ERMS miscellaneous entries using normalized attributes from {@see CashlessTollMiscellaneousMapper} (or equivalent).
     *
     * @param  array<string, mixed>  $miscAttributes
     * @return array{
     *   ok: bool,
     *   http_status: int|null,
     *   endpoint: string,
     *   request: array<string, mixed>|null,
     *   response: mixed,
     *   error: string|null
     * }
     */
    public function submit(array $miscAttributes): array
    {
        try {
            $payloadModel = $this->makePayloadModel($miscAttributes);
            $payloadModel->validateErmsMiscellaneousPayload();

            $signedPayload = $payloadModel->toErmsMiscellaneousPayload();
            $endpoint = (string) $payloadModel->getErmsMiscellaneousEndpoint();
            if ($endpoint === '') {
                throw new \RuntimeException('ERMS miscellaneous endpoint is empty. Configure erms.urls.miscellaneous_entries.');
            }

            $token = $this->getErmsAccessToken();
            $response = Http::timeout(30)
                ->withToken($token)
                ->withHeaders($payloadModel->toErmsMiscellaneousHeaders())
                ->post($endpoint, $signedPayload);

            $body = $response->json() ?? $response->body();
            $ok = $response->successful();

            Log::info('ERMS miscellaneous submission completed', [
                'ok' => $ok,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'response' => $body,
                'source_type' => (string) ($miscAttributes['source_type']),
                'toll_transaction_id' => (string) ($miscAttributes['tracking_reference'] ?? $miscAttributes['source_ref']),
            ]);

            return [
                'ok' => $ok,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'request' => $signedPayload,
                'response' => $body,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('ERMS miscellaneous submission failed', [
                'error' => $e->getMessage(),
                'source_type' => (string) ($miscAttributes['source_type']),
            ]);

            return [
                'ok' => false,
                'http_status' => null,
                'endpoint' => (string) config('erms.urls.miscellaneous_entries'),
                'request' => null,
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $miscAttributes
     */
    protected function makePayloadModel(array $miscAttributes): Model
    {
        $model = new class extends Model {
            use ErmsMiscellaneousPayloadTrait;
        };
        $model->setRawAttributes($miscAttributes, true);

        return $model;
    }
}
