<?php

namespace App\Services\Pos;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PosPaymentFlowLogger
{
    private const CHANNEL = 'pos_payment';

    public function start(Request $request, string $api): string
    {
        $flowId = (string) Str::uuid();

        Log::channel(self::CHANNEL)->info('[POS Payment] 1. Request received', [
            'flow_id' => $flowId,
            'api' => $api,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payment_type' => $this->detectPaymentType($request),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $request->all(),
        ]);

        return $flowId;
    }

    public function step(string $flowId, string $api, string $step, array $context = []): void
    {
        Log::channel(self::CHANNEL)->info('[POS Payment] ' . $step, array_merge([
            'flow_id' => $flowId,
            'api' => $api,
        ], $context));
    }

    public function respond(
        Request $request,
        string $api,
        string $flowId,
        JsonResponse $response,
        string $step,
        array $context = []
    ): JsonResponse {
        $body = json_decode($response->getContent(), true) ?? [];
        $success = ($body['success'] ?? false) === true;
        $level = $success ? 'info' : 'warning';

        Log::channel(self::CHANNEL)->{$level}('[POS Payment] Response sent', [
            'flow_id' => $flowId,
            'api' => $api,
            'step' => $step,
            'payment_type' => $this->detectPaymentType($request),
            'http_status' => $response->getStatusCode(),
            'success' => $success,
            'message' => $body['message'] ?? null,
            'request_payload' => $request->all(),
            'response' => $body,
            'context' => $context,
        ]);

        return $response;
    }

    public function exception(string $flowId, string $api, Request $request, \Throwable $e): void
    {
        Log::channel(self::CHANNEL)->error('[POS Payment] Exception', [
            'flow_id' => $flowId,
            'api' => $api,
            'payment_type' => $this->detectPaymentType($request),
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_payload' => $request->all(),
        ]);
    }

    /**
     * Log a successful balance deduction to the default Laravel log (storage/logs/laravel.log).
     */
    public function logDeduction(string $flowId, string $api, array $context): void
    {
        Log::info('[POS Balance Deduction] Deducted from account', array_merge([
            'flow_id' => $flowId,
            'api' => $api,
            'outcome' => 'success',
        ], $context));

        Log::channel(self::CHANNEL)->info('[POS Balance Deduction] Deducted from account', array_merge([
            'flow_id' => $flowId,
            'api' => $api,
            'outcome' => 'success',
        ], $context));
    }

    /**
     * Log a failed or blocked deduction attempt to the default Laravel log.
     */
    public function logDeductionFailed(string $flowId, string $api, string $reason, array $context): void
    {
        Log::warning('[POS Balance Deduction] Deduction not completed', array_merge([
            'flow_id' => $flowId,
            'api' => $api,
            'outcome' => 'failed',
            'reason' => $reason,
        ], $context));

        Log::channel(self::CHANNEL)->warning('[POS Balance Deduction] Deduction not completed', array_merge([
            'flow_id' => $flowId,
            'api' => $api,
            'outcome' => 'failed',
            'reason' => $reason,
        ], $context));
    }

    private function detectPaymentType(Request $request): string
    {
        if ($request->filled('card_reference')) {
            return 'nfc_card';
        }

        if ($request->filled('account_no')) {
            return 'qr_account';
        }

        return 'unknown';
    }
}
