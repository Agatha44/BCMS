<?php

namespace App\Traits\Erms;

use App\Services\Erms\ErmsPayloadSigner;

trait ErmsSignPayloadTrait
{
    /**
     * Build an ERMS request body: { "data": <payload>, "signature": "<base64>" }.
     *
     * @param  array<string, mixed>  $data  Inner payload (invoice, receipt, etc.).
     * @return array{data: array<string, mixed>, signature: string}
     */
    protected function signPayloadForErms(array $data): array
    {
        return app(ErmsPayloadSigner::class)->signDataEnvelope($data);
    }

    /**
     * Same as {@see signPayloadForErms} but accepts an array that already has a "data" key.
     *
     * @param  array<string, mixed>  $envelope
     * @return array{data: array<string, mixed>, signature: string}
     */
    protected function signErmsEnvelope(array $envelope): array
    {
        return app(ErmsPayloadSigner::class)->signEnvelope($envelope);
    }
}
