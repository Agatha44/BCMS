<?php

namespace App\Http\Controllers\Erms;

use App\Http\Controllers\Controller;
use App\Services\Erms\ErmsPayableCallbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErmsPayableCallbackController extends Controller
{
    public function __construct(
        protected ErmsPayableCallbackService $ermsPayableCallbackService
    ) {}

    /**
     * ERMS payable callback (GET query or POST JSON). Correlates by {@see sourceRef}: overtime batch
     * {@see OvertimeBatchPayableMapper} uses {@see OvertimeBatch::$batch_number}; payroll
     * {@see PayrollRunPayableMapper::sourceRef()} uses {@see PayrollRun::$payroll_number} or run id.
     */
    public function callback(Request $request): JsonResponse
    {
        $normalized = $this->ermsPayableCallbackService->normalizeFromRequest($request);
        $result = DB::connection('bcmis2')->transaction(function () use ($normalized) {
            return $this->ermsPayableCallbackService->handleErmsPayableCallback($normalized);
        });

        // caller can inspect ok/message.
        $status = ($result['message'] ?? '') !== '' && str_contains((string) $result['message'], 'Missing identifiers')
            ? 422
            : 200;

        return response()->json($result, $status);
    }
}
