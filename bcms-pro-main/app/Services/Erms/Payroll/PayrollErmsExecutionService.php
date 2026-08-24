<?php

namespace App\Services\Erms\Payroll;

use App\Models\Bms\Payroll\PayrollErmsExecution;
use App\Models\Bms\Payroll\PayrollRun;

class PayrollErmsExecutionService
{
    /**
     * @param  array<string, mixed>  $submission  ErmsMiscellaneousSubmissionService / ErmsPayableSubmissionService result
     */
    public function record(
        PayrollRun $run,
        string $executionType,
        string $sourceRef,
        array $submission,
        string $bankBatch = '',
    ): PayrollErmsExecution {
        $ok = (bool) ($submission['ok'] ?? false);
        $response = $submission['response'] ?? null;

        return PayrollErmsExecution::query()->updateOrCreate(
            [
                'payroll_run_id' => $run->id,
                'execution_type' => $executionType,
                'bank_batch' => $bankBatch,
            ],
            [
                'source_ref' => $sourceRef,
                'status' => $ok ? PayrollErmsExecution::STATUS_SUCCESS : PayrollErmsExecution::STATUS_FAILED,
                'erms_reference' => $this->extractErmsReference($response),
                'http_status' => isset($submission['http_status']) ? (int) $submission['http_status'] : null,
                'error_message' => $ok ? null : ($submission['error'] ?? $this->responseErrorMessage($response)),
                'response_payload' => $this->ermsResponseMessage($response),
                'submitted_at' => now(),
            ],
        );
    }

    /**
     * Slim ERMS response for DB audit — identifiers and messages only (no signature / full envelope).
     *
     * @param  mixed  $response
     * @return array<string, mixed>|null
     */
    public function ermsResponseMessage($response): ?array
    {
        if ($response === null) {
            return null;
        }

        if (! is_array($response)) {
            $message = trim((string) $response);

            return $message !== '' ? ['message' => $message] : null;
        }

        $data = isset($response['data']) && is_array($response['data'])
            ? $response['data']
            : $response;

        $out = [];

        foreach (['code', 'status', 'message', 'sourceRef', 'traceUid', 'ermsReference'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                $out[$key] = $data[$key];
            }
        }

        if (isset($data['payment']) && is_array($data['payment'])) {
            $payment = [];
            foreach (['requestId', 'requestNumber', 'sourceRef', 'stage', 'amount'] as $key) {
                if (isset($data['payment'][$key]) && $data['payment'][$key] !== null && $data['payment'][$key] !== '') {
                    $payment[$key] = $data['payment'][$key];
                }
            }
            if ($payment !== []) {
                $out['payment'] = $payment;
            }
        }

        foreach (['miscellaneousEntryId', 'journalNumber', 'referenceNumber', 'requestNumber'] as $key) {
            if (! array_key_exists($key, $out) && isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                $out[$key] = $data[$key];
            }
        }

        if ($out === []) {
            foreach (['message', 'error', 'errorMessage', 'code', 'status'] as $key) {
                if (isset($response[$key]) && $response[$key] !== null && $response[$key] !== ''
                    && ! in_array($key, ['data', 'signature', 'institutionCode'], true)) {
                    $out[$key] = $response[$key];
                }
            }
        }

        return $out !== [] ? $out : null;
    }

    /**
     * @param  mixed  $response
     */
    public function extractErmsReference($response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        $paths = [
            ['data', 'payment', 'requestNumber'],
            ['data', 'requestNumber'],
            ['data', 'referenceNumber'],
            ['data', 'journalNumber'],
            ['data', 'miscellaneousEntryId'],
            ['requestNumber'],
            ['referenceNumber'],
        ];

        foreach ($paths as $path) {
            $value = $response;
            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$segment];
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  mixed  $response
     */
    private function responseErrorMessage($response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        foreach (['message', 'error', 'errorMessage'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key]) && trim((string) $response[$key]) !== '') {
                return trim((string) $response[$key]);
            }
        }

        if (isset($response['data']) && is_array($response['data'])) {
            return $this->responseErrorMessage($response['data']);
        }

        return null;
    }
}
