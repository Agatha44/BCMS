<?php

namespace App\Services\Erms;

use App\Traits\Erms\ErmsAccessTokenTrait;
use App\Traits\Erms\ErmsReceiptPayloadTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ErmsReceiptSubmissionService
{
    use ErmsAccessTokenTrait;

    /**
     * Submit ERMS receipt using normalized receipt attributes from any mapper.
     *
     * @param  array<string, mixed>  $receiptAttributes
     * @param  array<string, mixed>|null  $bridgeBillAttributes
     * @return array{
     *   ok: bool,
     *   http_status: int|null,
     *   endpoint: string,
     *   request: array<string, mixed>|null,
     *   response: mixed,
     *   error: string|null
     * }
     */
    public function submit(array $receiptAttributes, ?array $bridgeBillAttributes = null): array
    {
        try {
            $payloadModel = $this->makePayloadModel($receiptAttributes, $bridgeBillAttributes);
            $payloadModel->validateErmsPayload();

            $signedPayload = $payloadModel->toErmsPayload();
            $endpoint = (string) $payloadModel->getErmsEndpoint();
            if ($endpoint === '') {
                throw new \RuntimeException('ERMS receipt endpoint is empty. Configure erms.urls.create_sale_receipt.');
            }

            $token = $this->getErmsAccessToken();
            $response = Http::timeout(30)
                ->withToken($token)
                ->withHeaders($payloadModel->toErmsHeaders())
                ->post($endpoint, $signedPayload);

            $body = $response->json() ?? $response->body();
            $ok = $response->successful();

            Log::info('ERMS receipt submission completed', [
                'ok' => $ok,
                'http_status' => $response->status(),
                'endpoint' => $endpoint,
                'source_type' => (string) ($receiptAttributes['source_type'] ?? 'unknown'),
                'bill_id' => (string) ($receiptAttributes['bill_id'] ?? ''),
                'receipt_number' => (string) ($receiptAttributes['receipt_number'] ?? ''),
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
            Log::error('ERMS receipt submission failed', [
                'error' => $e->getMessage(),
                'source_type' => (string) ($receiptAttributes['source_type'] ?? 'unknown'),
                'bill_id' => (string) ($receiptAttributes['bill_id'] ?? ''),
                'receipt_number' => (string) ($receiptAttributes['receipt_number'] ?? ''),
            ]);

            return [
                'ok' => false,
                'http_status' => null,
                'endpoint' => (string) config('erms.urls.create_sale_receipt', ''),
                'request' => null,
                'response' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $receiptAttributes
     * @param  array<string, mixed>|null  $bridgeBillAttributes
     */
    protected function makePayloadModel(array $receiptAttributes, ?array $bridgeBillAttributes): Model
    {
        $model = new class extends Model {
            use ErmsReceiptPayloadTrait;
        };

        $model->setRawAttributes($receiptAttributes, true);

        if (is_array($bridgeBillAttributes) && $bridgeBillAttributes !== []) {
            $bridgeBill = new BridgeBillPayloadAdapter($bridgeBillAttributes);
            $model->setRelation('bridgeBill', $bridgeBill);
        }

        return $model;
    }
}

class BridgeBillPayloadAdapter
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(private array $attributes)
    {
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }
}
