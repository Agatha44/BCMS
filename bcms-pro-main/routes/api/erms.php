<?php

use Illuminate\Support\Facades\Route;
use App\Services\Erms\ErmsPayloadSigner;
use App\Traits\Erms\ErmsAccessTokenTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Account;
use App\Models\BridgeBill;
use App\Models\EventPayment;
use App\Models\IncidentFine;
use App\Models\OverloadFine;
use App\Models\TollTransaction;
use App\Services\Erms\ErmsReceiptSubmissionService;
use App\Services\Erms\Mappers\BridgeBillReceiptMapper;
use App\Services\Erms\Mappers\EventPaymentReceiptMapper;
use App\Services\Erms\Mappers\IncidentFineReceiptMapper;
use App\Services\Erms\Mappers\OverloadFineReceiptMapper;
use App\Services\Erms\Mappers\CashlessTollMiscellaneousMapper;
use App\Services\Erms\Mappers\BundleSubscriptionMapper;
use App\Services\Erms\Mappers\PrepaymentMapper;
use App\Services\Erms\ErmsMiscellaneousSubmissionService;
use App\Models\Bms\OvertimeBatch;
use App\Services\Erms\Mappers\OvertimeBatchPayableMapper;
use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollTransaction;
use App\Services\Erms\ErmsPayableSubmissionService;
use App\Services\Erms\Mappers\PayrollRunPayableMapper;
use App\Services\Erms\Mappers\PayrollRunMiscellaneousMapper;
use App\Services\Erms\Mappers\PayrollNetPayMapper;
use App\Services\Erms\Mappers\PayrollDeductionPayableMapper;
use App\Models\Bms\Payroll\PayrollErmsExecution;
use App\Services\Erms\Payroll\BankResolver;
use App\Services\Erms\Payroll\PayrollErmsExecutionService;

// This file contains the routes for the ERMS API Testing

Route::get('erms/signature/health', function (ErmsPayloadSigner $signer) {
    try {
        $signed = $signer->signDataEnvelope([
            'ping' => 'pong',
            'ts' => now()->toIso8601String(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'ERMS signature is working.',
            'signature_length' => strlen($signed['signature']),
            'signed_at' => now()->toIso8601String(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => 'ERMS signature failed.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.signature.health');

Route::get('erms/overtime-batch/payable-payload-preview/{batch}', function (string $batch) {
    try {
        $lookup = trim($batch);

        $model = null;
        if ($lookup !== '' && ctype_digit($lookup)) {
            $model = OvertimeBatch::query()->find((int) $lookup);
        }
        if ($model === null && $lookup !== '') {
            $model = OvertimeBatch::query()->where('batch_number', $lookup)->first();
        }

        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Overtime batch not found.'], 404);
        }

        $mapper = app(OvertimeBatchPayableMapper::class);
        $unsigned = $mapper->map($model);
        $skipped = $mapper->skippedRequests();

        $preview = app(ErmsPayableSubmissionService::class)->previewPayablePayload($unsigned);

        return response()->json([
            'ok' => true,
            'message' => 'ERMS overtime payable payload preview generated.',
            'batch_id' => $model->id,
            'batch_number' => $model->batch_number,
            'batch_status' => $model->status,
            'endpoint' => $preview['endpoint'],
            'headers' => $preview['headers'],
            'skipped_requests' => $skipped,
            'unsigned_data' => $preview['unsigned_data'],
            'signed_payload' => $preview['signed_payload'],
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS overtime payable payload preview failed', [
            'batch' => $batch,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS overtime payable payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.overtime-batch.payable-payload-preview');

Route::post('erms/overtime-batch/payable-submit/{batch}', function (string $batch) {
    try {
        $lookup = trim($batch);

        $model = null;
        if ($lookup !== '' && ctype_digit($lookup)) {
            $model = OvertimeBatch::query()->find((int) $lookup);
        }
        if ($model === null && $lookup !== '') {
            $model = OvertimeBatch::query()->where('batch_number', $lookup)->first();
        }

        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Overtime batch not found.'], 404);
        }

        $unsigned = app(OvertimeBatchPayableMapper::class)->map($model);
        $submission = app(ErmsPayableSubmissionService::class)->submit($unsigned, [
            'batch_number' => $model->batch_number,
        ]);

        // Optional: persist response for audit/troubleshooting
        $model->external_response = is_array($submission['response']) ? $submission['response'] : ['raw' => $submission['response']];
        $model->erms_status = !empty($submission['ok']) ? 1 : 2;
        $response = $submission['response'] ?? null;
        $requestNumber = is_array($response)
            ? data_get($response, 'data.payment.requestNumber')
            : null;
        $model->payment_reference = ($requestNumber !== null && $requestNumber !== '')
            ? (string) $requestNumber
            : null;
        $model->save();

        return response()->json([
            'ok' => (bool) $submission['ok'],
            'message' => $submission['ok']
                ? 'Overtime batch payable request sent to ERMS successfully.'
                : 'Failed to send overtime batch payable request to ERMS.',
            'batch_id' => $model->id,
            'batch_number' => $model->batch_number,
            'batch_status' => $model->status,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS overtime payable submit failed', [
            'batch' => $batch,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS overtime payable request.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.overtime-batch.payable-submit');

Route::get('erms/payroll-run/payable-payload-preview/{run}', function (string $run) {
    try {
        $lookup = trim($run);
        if ($lookup === '' || ! ctype_digit($lookup)) {
            return response()->json(['ok' => false, 'message' => 'Payroll run id is required.'], 422);
        }

        $model = PayrollRun::query()->find((int) $lookup);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollRunPayableMapper::class);
        $unsigned = $mapper->map($model);
        $skipped = $mapper->skippedTransactions();

        $preview = app(ErmsPayableSubmissionService::class)->previewPayablePayload($unsigned);

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll payable payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'endpoint' => $preview['endpoint'],
            'headers' => $preview['headers'],
            'skipped_transactions' => $skipped,
            'unsigned_data' => $preview['unsigned_data'],
            'signed_payload' => $preview['signed_payload'],
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll payable payload preview failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll payable payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.payable-payload-preview');

Route::post('erms/payroll-run/payable-submit/{run}', function (string $run) {
    try {
        $lookup = trim($run);
        if ($lookup === '' || ! ctype_digit($lookup)) {
            return response()->json(['ok' => false, 'message' => 'Payroll run id is required.'], 422);
        }

        $model = PayrollRun::query()->find((int) $lookup);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        Log::info('Payroll run found', [
            'model' => $model,
        ]);

        $unsigned = app(PayrollRunPayableMapper::class)->map($model);
        $submission = app(ErmsPayableSubmissionService::class)->submit($unsigned, [
            'payroll_run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
        ]);

        Log::info('Payroll submitted to ERMS', [
            'submission' => $submission,
        ]);

        if ($submission['ok']) {

            $model->update([
                'erms_status' => 1,
                'erms_submitted_at' => now(),
                'erms_reference' => $submission['response']['data']['payment']['requestNumber'],
            ]);
        } else {
            $model->update([
                'erms_status' => 2,
                'erms_submitted_at' => now(),
                'erms_reference' => null,
            ]);
        }

        $payableExecution = app(PayrollErmsExecutionService::class)->record(
            $model,
            PayrollErmsExecution::TYPE_PAYABLE,
            (string) ($unsigned['sourceRef'] ?? $model->payroll_number),
            $submission,
        );

        return response()->json([
            'ok' => (bool) $submission['ok'],
            'message' => $submission['ok']
                ? 'Payroll payable request sent to ERMS successfully.'
                : 'Failed to send payroll payable request to ERMS.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
            'erms_execution_id' => $payableExecution->id,
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll payable submit failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll payable request.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.payable-submit');

Route::get('erms/payroll-run/miscellaneous-payload-preview/{run}', function (string $run) {
    try {
        $lookup = trim($run);
        if ($lookup === '' || ! ctype_digit($lookup)) {
            return response()->json(['ok' => false, 'message' => 'Payroll run id is required.'], 422);
        }

        $model = PayrollRun::query()->find((int) $lookup);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $miscAttributes = app(PayrollRunMiscellaneousMapper::class)->map($model);

        $payloadModel = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Traits\Erms\ErmsMiscellaneousPayloadTrait;
        };
        $payloadModel->setRawAttributes($miscAttributes, true);
        $payloadModel->validateErmsMiscellaneousPayload();

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll miscellaneous payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'source_ref' => $miscAttributes['source_ref'] ?? null,
            'endpoint' => $payloadModel->getErmsMiscellaneousEndpoint(),
            'headers' => $payloadModel->toErmsMiscellaneousHeaders(),
            'journal_totals' => $miscAttributes['journal_totals'] ?? null,
            'misc_attributes' => $miscAttributes,
            'unsigned_data' => $payloadModel->toMiscellaneousEntryData(),
            'signed_payload' => $payloadModel->toErmsMiscellaneousPayload(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll miscellaneous payload preview failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll miscellaneous payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.miscellaneous-payload-preview');

Route::post('erms/payroll-run/miscellaneous-submit/{run}', function (string $run) {
    try {
        $lookup = trim($run);
        if ($lookup === '' || ! ctype_digit($lookup)) {
            return response()->json(['ok' => false, 'message' => 'Payroll run id is required.'], 422);
        }

        $model = PayrollRun::query()->find((int) $lookup);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $miscAttributes = app(PayrollRunMiscellaneousMapper::class)->map($model);
        $submission = app(ErmsMiscellaneousSubmissionService::class)->submit($miscAttributes);

        $miscExecution = app(PayrollErmsExecutionService::class)->record(
            $model,
            PayrollErmsExecution::TYPE_MISCELLANEOUS,
            (string) ($miscAttributes['source_ref'] ?? $model->payroll_number.'-MISC'),
            $submission,
        );

        Log::info('Payroll miscellaneous submitted to ERMS', [
            'payroll_run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'source_ref' => $miscAttributes['source_ref'] ?? null,
            'submission_ok' => $submission['ok'] ?? false,
            'erms_execution_id' => $miscExecution->id,
        ]);

        return response()->json([
            'ok' => (bool) ($submission['ok'] ?? false),
            'message' => ($submission['ok'] ?? false)
                ? 'Payroll miscellaneous entry sent to ERMS successfully.'
                : 'Failed to send payroll miscellaneous entry to ERMS.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'source_ref' => $miscAttributes['source_ref'] ?? null,
            'erms_execution_id' => $miscExecution->id,
            'erms_execution_status' => $miscExecution->status,
            'http_status' => $submission['http_status'] ?? null,
            'endpoint' => $submission['endpoint'] ?? null,
            'response' => $submission['response'] ?? null,
            'error' => $submission['error'] ?? null,
        ], ($submission['ok'] ?? false) ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll miscellaneous submit failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll miscellaneous entry.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.miscellaneous-submit');

$resolvePayrollRun = static function (string $lookup): ?PayrollRun {
    $lookup = trim($lookup);
    if ($lookup === '') {
        return null;
    }
    if (ctype_digit($lookup)) {
        return PayrollRun::query()->find((int) $lookup);
    }

    return PayrollRun::query()->where('payroll_number', $lookup)->first();
};

Route::get('erms/payroll-run/net-pay/summary/{run}', function (string $run) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollNetPayMapper::class);

        return response()->json([
            'ok' => true,
            'message' => 'Payroll net-pay bank bucket summary generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'buckets' => $mapper->summarizeBuckets($model),
            'skipped_transactions' => $mapper->skippedTransactions(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll net-pay bank summary failed', ['run' => $run, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not summarize payroll net-pay bank buckets.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.net-pay.summary');

Route::get('erms/payroll-run/net-pay/payload-preview/{run}', function (string $run) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollNetPayMapper::class);
        $bucketFilter = strtolower(trim((string) request()->query('bucket', '')));
        $payableService = app(ErmsPayableSubmissionService::class);

        $payloads = $bucketFilter !== ''
            ? [$bucketFilter => $mapper->mapBucket($model, $bucketFilter)]
            : $mapper->mapAll($model);

        $buckets = [];
        foreach ($payloads as $bucketKey => $unsigned) {
            $preview = $payableService->previewPayablePayload($unsigned);
            $buckets[] = [
                'bucket' => $bucketKey,
                'label' => $mapper->bankLabel($bucketKey),
                'source_ref' => $unsigned['sourceRef'] ?? null,
                'amount' => $unsigned['amount'] ?? null,
                'payee_count' => is_array($unsigned['payeeList'] ?? null) ? count($unsigned['payeeList']) : 0,
                'endpoint' => $preview['endpoint'],
                'headers' => $preview['headers'],
                'unsigned_data' => $preview['unsigned_data'],
                'signed_payload' => $preview['signed_payload'],
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll net-pay by bank payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'buckets' => $buckets,
            'skipped_transactions' => $mapper->skippedTransactions(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll net-pay by bank payload preview failed', ['run' => $run, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll net-pay by bank payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.net-pay.payload-preview');

Route::get('erms/payroll-run/net-pay/payload-preview/{run}/{bucket}', function (string $run, string $bucket) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollNetPayMapper::class);
        $unsigned = $mapper->mapBucket($model, $bucket);
        $preview = app(ErmsPayableSubmissionService::class)->previewPayablePayload($unsigned);

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll net-pay bank payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'bucket' => $bucket,
            'label' => $mapper->bankLabel($bucket),
            'source_ref' => $unsigned['sourceRef'] ?? null,
            'amount' => $unsigned['amount'] ?? null,
            'payee_count' => is_array($unsigned['payeeList'] ?? null) ? count($unsigned['payeeList']) : 0,
            'endpoint' => $preview['endpoint'],
            'headers' => $preview['headers'],
            'unsigned_data' => $preview['unsigned_data'],
            'signed_payload' => $preview['signed_payload'],
            'skipped_transactions' => $mapper->skippedTransactions(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll net-pay by bank payload preview failed', [
            'run' => $run,
            'bucket' => $bucket,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll net-pay bank payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.net-pay.payload-preview.bucket');

$submitPayrollNetPayBucket = static function (
    PayrollRun $model,
    array $unsigned,
    string $bucketKey,
): array {
    $label = app(BankResolver::class)->label($bucketKey);

    $submission = app(ErmsPayableSubmissionService::class)->submit($unsigned, [
        'payroll_run_id' => $model->id,
        'payroll_number' => $model->payroll_number,
        'bank_bucket' => $bucketKey,
        'source_ref' => $unsigned['sourceRef'] ?? null,
    ]);

    $execution = app(PayrollErmsExecutionService::class)->record(
        $model,
        PayrollErmsExecution::TYPE_NET_PAY_PAYABLE,
        (string) ($unsigned['sourceRef'] ?? ''),
        $submission,
        $bucketKey,
    );

    Log::info('Payroll net-pay by bank submitted to ERMS', [
        'payroll_run_id' => $model->id,
        'payroll_number' => $model->payroll_number,
        'bank_bucket' => $bucketKey,
        'source_ref' => $unsigned['sourceRef'] ?? null,
        'submission_ok' => $submission['ok'] ?? false,
        'erms_execution_id' => $execution->id,
    ]);

    return [
        'bucket' => $bucketKey,
        'label' => $label,
        'bank_batch' => $execution->bank_batch,
        'source_ref' => $unsigned['sourceRef'] ?? null,
        'ok' => (bool) ($submission['ok'] ?? false),
        'erms_execution_id' => $execution->id,
        'erms_execution_status' => $execution->status,
        'amount' => $unsigned['amount'] ?? null,
        'payee_count' => is_array($unsigned['payeeList'] ?? null) ? count($unsigned['payeeList']) : 0,
        'endpoint' => $submission['endpoint'] ?? null,
        'http_status' => $submission['http_status'] ?? null,
        'response' => $submission['response'] ?? null,
        'error' => $submission['error'] ?? null,
    ];
};

Route::post('erms/payroll-run/net-pay/submit/{run}', function (string $run) use ($resolvePayrollRun, $submitPayrollNetPayBucket) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollNetPayMapper::class);
        $bucketFilter = strtolower(trim((string) request()->query('bucket', '')));

        $payloads = $bucketFilter !== ''
            ? [$bucketFilter => $mapper->mapBucket($model, $bucketFilter)]
            : $mapper->mapAll($model);

        if ($payloads === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No net-pay bank buckets with payees to submit for this payroll run.',
                'run_id' => $model->id,
                'payroll_number' => $model->payroll_number,
                'buckets' => [],
                'skipped_transactions' => $mapper->skippedTransactions(),
            ], 422);
        }

        $buckets = [];
        $allOk = true;

        foreach ($payloads as $bucketKey => $unsigned) {
            $result = $submitPayrollNetPayBucket($model, $unsigned, (string) $bucketKey);
            $buckets[] = $result;
            if (! $result['ok']) {
                $allOk = false;
            }
        }

        $submitted = count(array_filter($buckets, static fn (array $row) => $row['ok']));
        $failed = count($buckets) - $submitted;

        return response()->json([
            'ok' => $allOk,
            'message' => $allOk
                ? 'All payroll net-pay bank payables sent to ERMS successfully.'
                : "Payroll net-pay submit finished with {$failed} failed bucket(s) of ".count($buckets).'.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'submitted_count' => $submitted,
            'failed_count' => $failed,
            'buckets' => $buckets,
            'skipped_transactions' => $mapper->skippedTransactions(),
        ], $allOk ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll net-pay submit (all buckets) failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll net-pay payables for this run.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.net-pay.submit');

Route::post('erms/payroll-run/net-pay/submit/{run}/{bucket}', function (string $run, string $bucket) use ($resolvePayrollRun, $submitPayrollNetPayBucket) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollNetPayMapper::class);
        $unsigned = $mapper->mapBucket($model, $bucket);
        $result = $submitPayrollNetPayBucket($model, $unsigned, $bucket);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Payroll net-pay bank payable sent to ERMS successfully.'
                : 'Failed to send payroll net-pay bank payable to ERMS.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'bank_bucket' => $result['bucket'],
            'bank_batch' => $result['bank_batch'],
            'source_ref' => $result['source_ref'],
            'erms_execution_id' => $result['erms_execution_id'],
            'erms_execution_status' => $result['erms_execution_status'],
            'endpoint' => $result['endpoint'],
            'http_status' => $result['http_status'],
            'response' => $result['response'],
            'error' => $result['error'],
            'skipped_transactions' => $mapper->skippedTransactions(),
        ], $result['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll net-pay by bank submit failed', [
            'run' => $run,
            'bucket' => $bucket,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll net-pay bank payable.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.net-pay.submit.bucket');

$submitPayrollDeductionPayable = static function (
    PayrollRun $model,
    array $unsigned,
    string $kind,
): array {
    $submission = app(ErmsPayableSubmissionService::class)->submit($unsigned, [
        'payroll_run_id' => $model->id,
        'payroll_number' => $model->payroll_number,
        'deduction_kind' => $unsigned['deduction_kind'] ?? $kind,
        'source_ref' => $unsigned['sourceRef'] ?? null,
    ]);

    $execution = app(PayrollErmsExecutionService::class)->record(
        $model,
        PayrollDeductionPayableMapper::executionType($kind),
        (string) ($unsigned['sourceRef'] ?? ''),
        $submission,
        (string) ($unsigned['deduction_kind'] ?? $kind),
    );

    Log::info('Payroll deduction payable submitted to ERMS', [
        'payroll_run_id' => $model->id,
        'payroll_number' => $model->payroll_number,
        'deduction_kind' => $kind,
        'source_ref' => $unsigned['sourceRef'] ?? null,
        'submission_ok' => $submission['ok'] ?? false,
        'erms_execution_id' => $execution->id,
    ]);

    return [
        'kind' => $unsigned['deduction_kind'] ?? $kind,
        'label' => $unsigned['deduction_label'] ?? $kind,
        'bank_batch' => $execution->bank_batch,
        'source_ref' => $unsigned['sourceRef'] ?? null,
        'ok' => (bool) ($submission['ok'] ?? false),
        'erms_execution_id' => $execution->id,
        'erms_execution_status' => $execution->status,
        'amount' => $unsigned['amount'] ?? null,
        'deduction_amounts' => $unsigned['deduction_amounts'] ?? null,
        'endpoint' => $submission['endpoint'] ?? null,
        'http_status' => $submission['http_status'] ?? null,
        'response' => $submission['response'] ?? null,
        'error' => $submission['error'] ?? null,
    ];
};

Route::get('erms/payroll-run/deduction-payable/kinds', function () {
    try {
        $kinds = app(PayrollDeductionPayableMapper::class)->kinds();

        return response()->json([
            'ok' => true,
            'kinds' => $kinds,
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable kinds failed', ['error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not list payroll deduction payable kinds.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.kinds');

Route::get('erms/payroll-run/deduction-payable/summary/{run}', function (string $run) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);

        return response()->json([
            'ok' => true,
            'message' => 'Payroll deduction payable summary generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'payables' => $mapper->summarizeAll($model),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable summary failed', ['run' => $run, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not summarize payroll deduction payables.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.summary');

Route::get('erms/payroll-run/deduction-payable/summary/{run}/{kind}', function (string $run, string $kind) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);

        return response()->json([
            'ok' => true,
            'message' => 'Payroll deduction payable summary generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'payable' => $mapper->summarize($model, $kind),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable summary failed', [
            'run' => $run,
            'kind' => $kind,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not summarize payroll deduction payable.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.summary.kind');

Route::get('erms/payroll-run/deduction-payable/payload-preview/{run}', function (string $run) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);
        $kindFilter = strtolower(trim((string) request()->query('kind', '')));
        $payableService = app(ErmsPayableSubmissionService::class);

        $payloads = $kindFilter !== ''
            ? [$kindFilter => $mapper->map($model, $kindFilter)]
            : $mapper->mapAll($model);

        $payables = [];
        foreach ($payloads as $kindKey => $unsigned) {
            $preview = $payableService->previewPayablePayload($unsigned);
            $payables[] = [
                'kind' => $kindKey,
                'label' => $unsigned['deduction_label'] ?? $kindKey,
                'source_ref' => $unsigned['sourceRef'] ?? null,
                'amount' => $unsigned['amount'] ?? null,
                'deduction_amounts' => $unsigned['deduction_amounts'] ?? null,
                'endpoint' => $preview['endpoint'],
                'headers' => $preview['headers'],
                'unsigned_data' => $preview['unsigned_data'],
                'signed_payload' => $preview['signed_payload'],
            ];
        }

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll deduction payable payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'payables' => $payables,
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable payload preview failed', ['run' => $run, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll deduction payable payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.payload-preview');

Route::get('erms/payroll-run/deduction-payable/payload-preview/{run}/{kind}', function (string $run, string $kind) use ($resolvePayrollRun) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);
        $unsigned = $mapper->map($model, $kind);
        $preview = app(ErmsPayableSubmissionService::class)->previewPayablePayload($unsigned);

        return response()->json([
            'ok' => true,
            'message' => 'ERMS payroll deduction payable payload preview generated.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'run_status' => $model->status,
            'kind' => $unsigned['deduction_kind'] ?? $kind,
            'label' => $unsigned['deduction_label'] ?? null,
            'source_ref' => $unsigned['sourceRef'] ?? null,
            'amount' => $unsigned['amount'] ?? null,
            'deduction_amounts' => $unsigned['deduction_amounts'] ?? null,
            'endpoint' => $preview['endpoint'],
            'headers' => $preview['headers'],
            'unsigned_data' => $preview['unsigned_data'],
            'signed_payload' => $preview['signed_payload'],
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable payload preview failed', [
            'run' => $run,
            'kind' => $kind,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS payroll deduction payable payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.payload-preview.kind');

Route::post('erms/payroll-run/deduction-payable/submit/{run}', function (string $run) use ($resolvePayrollRun, $submitPayrollDeductionPayable) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);
        $kindFilter = strtolower(trim((string) request()->query('kind', '')));

        $payloads = $kindFilter !== ''
            ? [$kindFilter => $mapper->map($model, $kindFilter)]
            : $mapper->mapAll($model);

        if ($payloads === []) {
            return response()->json([
                'ok' => false,
                'message' => 'No deduction payables with amounts to submit for this payroll run.',
                'run_id' => $model->id,
                'payroll_number' => $model->payroll_number,
                'payables' => [],
            ], 422);
        }

        $results = [];
        $allOk = true;

        foreach ($payloads as $kindKey => $unsigned) {
            $result = $submitPayrollDeductionPayable($model, $unsigned, (string) $kindKey);
            $results[] = $result;
            if (! $result['ok']) {
                $allOk = false;
            }
        }

        $submitted = count(array_filter($results, static fn (array $row) => $row['ok']));
        $failed = count($results) - $submitted;

        return response()->json([
            'ok' => $allOk,
            'message' => $allOk
                ? 'All payroll deduction payables sent to ERMS successfully.'
                : "Deduction payable submit finished with {$failed} failed of ".count($results).'.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'submitted_count' => $submitted,
            'failed_count' => $failed,
            'payables' => $results,
        ], $allOk ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable submit (all) failed', [
            'run' => $run,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll deduction payables.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.submit');

Route::post('erms/payroll-run/deduction-payable/submit/{run}/{kind}', function (string $run, string $kind) use ($resolvePayrollRun, $submitPayrollDeductionPayable) {
    try {
        $model = $resolvePayrollRun($run);
        if ($model === null) {
            return response()->json(['ok' => false, 'message' => 'Payroll run not found.'], 404);
        }

        $mapper = app(PayrollDeductionPayableMapper::class);
        $unsigned = $mapper->map($model, $kind);
        $result = $submitPayrollDeductionPayable($model, $unsigned, $kind);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Payroll deduction payable sent to ERMS successfully.'
                : 'Failed to send payroll deduction payable to ERMS.',
            'run_id' => $model->id,
            'payroll_number' => $model->payroll_number,
            'kind' => $result['kind'],
            'label' => $result['label'],
            'bank_batch' => $result['bank_batch'],
            'source_ref' => $result['source_ref'],
            'erms_execution_id' => $result['erms_execution_id'],
            'erms_execution_status' => $result['erms_execution_status'],
            'deduction_amounts' => $result['deduction_amounts'],
            'endpoint' => $result['endpoint'],
            'http_status' => $result['http_status'],
            'response' => $result['response'],
            'error' => $result['error'],
        ], $result['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS payroll deduction payable submit failed', [
            'run' => $run,
            'kind' => $kind,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not submit ERMS payroll deduction payable.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.payroll-run.deduction-payable.submit.kind');

Route::get('erms/overload-fine/payload-preview/{id}', function (int $id) {
    try {
        $fine = OverloadFine::query()->find($id);
        if ($fine === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Overload fine not found.',
            ], 404);
        }

        $mapper = app(OverloadFineReceiptMapper::class);
        $attrs = $mapper->map($fine);

        $payloadModel = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Traits\Erms\ErmsReceiptPayloadTrait;
        };
        $payloadModel->setRawAttributes($attrs, true);
        $payloadModel->validateErmsPayload();

        $unsigned = $payloadModel->toReceivableReceiptData();
        $signed = $payloadModel->toErmsPayload();

        return response()->json([
            'ok' => true,
            'message' => 'ERMS overload fine receipt payload preview generated.',
            'overload_fine_id' => $fine->id,
            'endpoint' => $payloadModel->getErmsEndpoint(),
            'headers' => $payloadModel->toErmsHeaders(),
            'mapped_attributes' => $attrs,
            'unsigned_data' => $unsigned,
            'signed_payload' => $signed,
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS overload fine payload preview failed', [
            'overload_fine_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS overload fine payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.overload-fine.payload-preview');

Route::post('erms/overload-fine/create-sale-receipt/{id}', function (int $id) {
    try {
        $fine = OverloadFine::query()->find($id);
        if ($fine === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Overload fine not found.',
            ], 404);
        }

        if ((int) ($fine->erp_status ?? 0) === 1) {
            return response()->json([
                'ok' => true,
                'message' => 'Skipping ERMS submit; already posted.',
                'overload_fine_id' => $fine->id,
                'http_status' => $fine->http_status,
            ]);
        }

        $mapper = app(OverloadFineReceiptMapper::class);
        $submission = app(ErmsReceiptSubmissionService::class)->submit($mapper->map($fine));

        DB::table('overload_fine')
            ->where('id', $fine->id)
            ->update([
                'erp_status' => $submission['ok'] ? 1 : 2,
                'http_status' => $submission['http_status'],
                'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
            ]);

        return response()->json([
            'ok' => $submission['ok'],
            'message' => $submission['ok']
                ? 'Overload fine receipt sent to ERMS successfully.'
                : 'Failed to send overload fine receipt to ERMS.',
            'overload_fine_id' => $fine->id,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS overload fine create receipt failed', [
            'overload_fine_id' => $id,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not send ERMS overload fine receipt.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.overload-fine.create-sale-receipt');

/*
|--------------------------------------------------------------------------
| Additional payment types (mapper + ErmsReceiptSubmissionService)
|--------------------------------------------------------------------------
*/

$ermsReceiptPreviewFromMapper = static function (array $attrs, string $resourceLabel, mixed $resourceId): \Illuminate\Http\JsonResponse {
    try {
        $payloadModel = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Traits\Erms\ErmsReceiptPayloadTrait;
        };
        $payloadModel->setRawAttributes($attrs, true);
        $payloadModel->validateErmsPayload();
        $unsigned = $payloadModel->toReceivableReceiptData();
        $signed = $payloadModel->toErmsPayload();

        return response()->json([
            'ok' => true,
            'message' => "ERMS {$resourceLabel} receipt payload preview generated.",
            'resource_id' => $resourceId,
            'endpoint' => $payloadModel->getErmsEndpoint(),
            'headers' => $payloadModel->toErmsHeaders(),
            'mapped_attributes' => $attrs,
            'unsigned_data' => $unsigned,
            'signed_payload' => $signed,
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS receipt payload preview failed', [
            'resource' => $resourceLabel,
            'resource_id' => $resourceId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => "Could not generate ERMS {$resourceLabel} payload preview.",
            'error' => $e->getMessage(),
        ], 500);
    }
};

Route::get('erms/incident-fine/payload-preview/{id}', function (int $id) use ($ermsReceiptPreviewFromMapper) {
    $fine = IncidentFine::query()->find($id);
    if ($fine === null) {
        return response()->json(['ok' => false, 'message' => 'Incident fine not found.'], 404);
    }
    $attrs = app(IncidentFineReceiptMapper::class)->map($fine);

    return $ermsReceiptPreviewFromMapper($attrs, 'incident fine', $id);
})->name('erms.incident-fine.payload-preview');

Route::post('erms/incident-fine/create-sale-receipt/{id}', function (int $id) {
    try {
        $fine = IncidentFine::query()->find($id);
        if ($fine === null) {
            return response()->json(['ok' => false, 'message' => 'Incident fine not found.'], 404);
        }
        if ((int) ($fine->erp_status ?? 0) === 1) {
            return response()->json([
                'ok' => true,
                'message' => 'Skipping ERMS submit; already posted.',
                'incident_fine_id' => $fine->id,
                'http_status' => $fine->http_status,
            ]);
        }
        $submission = app(ErmsReceiptSubmissionService::class)->submit(app(IncidentFineReceiptMapper::class)->map($fine));
        DB::table('incident_fine')->where('id', $fine->id)->update([
            'erp_status' => $submission['ok'] ? 1 : 2,
            'http_status' => $submission['http_status'],
            'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
        ]);

        return response()->json([
            'ok' => $submission['ok'],
            'message' => $submission['ok']
                ? 'Incident fine receipt sent to ERMS successfully.'
                : 'Failed to send incident fine receipt to ERMS.',
            'incident_fine_id' => $fine->id,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS incident fine create receipt failed', ['incident_fine_id' => $id, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not send ERMS incident fine receipt.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.incident-fine.create-sale-receipt');

Route::get('erms/event-payment/payload-preview/{id}', function (int $id) use ($ermsReceiptPreviewFromMapper) {
    $payment = EventPayment::query()->find($id);
    if ($payment === null) {
        return response()->json(['ok' => false, 'message' => 'Event payment not found.'], 404);
    }
    $attrs = app(EventPaymentReceiptMapper::class)->map($payment);

    return $ermsReceiptPreviewFromMapper($attrs, 'event payment', $id);
})->name('erms.event-payment.payload-preview');

Route::post('erms/event-payment/create-sale-receipt/{id}', function (int $id) {
    try {
        $payment = EventPayment::query()->find($id);
        if ($payment === null) {
            return response()->json(['ok' => false, 'message' => 'Event payment not found.'], 404);
        }
        if ((int) ($payment->erp_status ?? 0) === 1) {
            return response()->json([
                'ok' => true,
                'message' => 'Skipping ERMS submit; already posted.',
                'event_payment_id' => $payment->id,
                'http_status' => $payment->http_status,
            ]);
        }
        $submission = app(ErmsReceiptSubmissionService::class)->submit(app(EventPaymentReceiptMapper::class)->map($payment));
        DB::table('event_payment')->where('id', $payment->id)->update([
            'erp_status' => $submission['ok'] ? 1 : 2,
            'http_status' => $submission['http_status'],
            'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
        ]);

        return response()->json([
            'ok' => $submission['ok'],
            'message' => $submission['ok']
                ? 'Event payment receipt sent to ERMS successfully.'
                : 'Failed to send event payment receipt to ERMS.',
            'event_payment_id' => $payment->id,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS event payment create receipt failed', ['event_payment_id' => $id, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not send ERMS event payment receipt.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.event-payment.create-sale-receipt');

Route::get('erms/bridge-bill/payload-preview/{id}', function (int $id) use ($ermsReceiptPreviewFromMapper) {
    $bill = BridgeBill::query()->find($id);
    if ($bill === null) {
        return response()->json(['ok' => false, 'message' => 'Bridge bill not found.'], 404);
    }
    $attrs = app(BridgeBillReceiptMapper::class)->map($bill);

    return $ermsReceiptPreviewFromMapper($attrs, 'bridge bill', $id);
})->name('erms.bridge-bill.payload-preview');

Route::post('erms/bridge-bill/create-sale-receipt/{id}', function (int $id) {
    try {
        $bill = BridgeBill::query()->find($id);
        if ($bill === null) {
            return response()->json(['ok' => false, 'message' => 'Bridge bill not found.'], 404);
        }
        if ((int) ($bill->erp_status ?? 0) === 1) {
            return response()->json([
                'ok' => true,
                'message' => 'Skipping ERMS submit; already posted.',
                'bridge_bill_id' => $bill->id,
                'http_status' => $bill->http_status,
            ]);
        }
        $submission = app(ErmsReceiptSubmissionService::class)->submit(app(BridgeBillReceiptMapper::class)->map($bill));
        DB::table('bridge_bills')->where('id', $bill->id)->update([
            'erp_status' => $submission['ok'] ? 1 : 2,
            'http_status' => $submission['http_status'],
            'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => $submission['ok'],
            'message' => $submission['ok']
                ? 'Bridge bill receipt sent to ERMS successfully.'
                : 'Failed to send bridge bill receipt to ERMS.',
            'bridge_bill_id' => $bill->id,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS bridge bill create receipt failed', ['bridge_bill_id' => $id, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not send ERMS bridge bill receipt.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.bridge-bill.create-sale-receipt');

Route::get('erms/top-up/payload-preview/{id}', function (int $id) use ($ermsReceiptPreviewFromMapper) {
    $row = DB::table('top_up')->where('id', $id)->first();
    if ($row === null) {
        return response()->json(['ok' => false, 'message' => 'Top up not found.'], 404);
    }
    $attrs = app(PrepaymentMapper::class)->map($row);

    return $ermsReceiptPreviewFromMapper($attrs, 'top up (prepayment)', $id);
})->name('erms.top-up.payload-preview');

Route::post('erms/top-up/create-sale-receipt/{id}', function (int $id) {
    try {
        $row = DB::table('top_up')->where('id', $id)->first();
        if ($row === null) {
            return response()->json(['ok' => false, 'message' => 'Top up not found.'], 404);
        }
        if ((int) ($row->erp_status ?? 0) === 1) {
            return response()->json([
                'ok' => true,
                'message' => 'Skipping ERMS submit; already posted.',
                'top_up_id' => $row->id,
                'http_status' => $row->http_status,
            ]);
        }
        $submission = app(ErmsReceiptSubmissionService::class)->submit(app(PrepaymentMapper::class)->map($row));
        DB::table('top_up')->where('id', $row->id)->update([
            'erp_status' => $submission['ok'] ? 1 : 2,
            'http_status' => $submission['http_status'],
            'receipt_date' => $submission['ok'] ? now() : DB::raw('receipt_date'),
        ]);

        return response()->json([
            'ok' => $submission['ok'],
            'message' => $submission['ok']
                ? 'Top up receipt sent to ERMS successfully.'
                : 'Failed to send top up receipt to ERMS.',
            'top_up_id' => $row->id,
            'endpoint' => $submission['endpoint'],
            'http_status' => $submission['http_status'],
            'response' => $submission['response'],
            'error' => $submission['error'],
        ], $submission['ok'] ? 200 : 500);
    } catch (\Throwable $e) {
        Log::error('ERMS top up create receipt failed', ['top_up_id' => $id, 'error' => $e->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not send ERMS top up receipt.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.top-up.create-sale-receipt');

Route::get('erms/receipt/config-check', function () {
    $receiptCfg = config('erms.receivable_receipt', config('services.erms.receivable_receipt', []));
    $defaultCfg = config('erms.default_config', config('services.erms.default_config', []));
    $receiptCfg = is_array($receiptCfg) ? $receiptCfg : [];
    $gepgCfg = config('erms.gepg', config('services.erms.gepg', []));
    $gepgCfg = is_array($gepgCfg) ? $gepgCfg : [];

    $resolved = [
        'branch_code' => (string) (($defaultCfg['branch_code'] ?? $gepgCfg['sp_code'] ?? '') ?? ''),
        'department_code' => (string) (($defaultCfg['department_code'] ?? $gepgCfg['vote_code'] ?? '') ?? ''),
        'debit_account_code' => (string) (($receiptCfg['debit_account_code'] ?? '') ?? ''),
        'credit_account_code' => (string) (($receiptCfg['credit_account_code'] ?? '') ?? ''),
        'debit_gfs_code' => (string) (($receiptCfg['debit_gfs_code'] ?? $gepgCfg['gfs_code'] ?? '') ?? ''),
        'credit_gfs_code' => (string) (($receiptCfg['credit_gfs_code'] ?? $gepgCfg['gfs_code'] ?? '') ?? ''),
        'generated_by' => (string) (($defaultCfg['generated_by'] ?? '') ?? ''),
    ];

    $missing = [];
    foreach ($resolved as $key => $value) {
        if ($value === '') {
            $missing[] = $key;
        }
    }

    return response()->json([
        'ok' => $missing === [],
        'message' => $missing === [] ? 'ERMS receipt config is complete.' : 'ERMS receipt config has missing required values.',
        'resolved' => $resolved,
        'missing' => $missing,
    ]);
})->name('erms.receipt.config-check');

Route::get('erms/miscellaneous/payload-preview/cashless/{tollId}', function (int $tollId) {
    $accountNo = null;

    try {
        $toll = TollTransaction::query()->find($tollId);
        if ($toll === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Toll transaction not found.',
            ], 404);
        }

        $accountNo = (string) ($toll->account_no);
        $account = Account::query()->where('account_no', $accountNo)->first();
        if ($account === null) {
            return response()->json(['ok' => false, 'message' => 'Account not found.'], 404);
        }

        $mapper = app(CashlessTollMiscellaneousMapper::class);
        $attrs = $mapper->map($toll, $account);

        $payloadModel = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Traits\Erms\ErmsMiscellaneousPayloadTrait;
        };
        $payloadModel->setRawAttributes($attrs, true);
        $payloadModel->validateErmsMiscellaneousPayload();

        return response()->json([
            'ok' => true,
            'message' => 'ERMS miscellaneous payload preview (cashless toll) generated.',
            'toll_transaction_id' => $toll->id,
            'account_no' => $accountNo,
            'endpoint' => $payloadModel->getErmsMiscellaneousEndpoint(),
            'headers' => $payloadModel->toErmsMiscellaneousHeaders(),
            'mapped_attributes' => $attrs,
            'unsigned_data' => $payloadModel->toMiscellaneousEntryData(),
            'signed_payload' => $payloadModel->toErmsMiscellaneousPayload(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS miscellaneous cashless payload preview failed', [
            'toll_transaction_id' => $tollId,
            'account_no' => $accountNo,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS miscellaneous cashless payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.miscellaneous.payload-preview.cashless');

Route::post('erms/miscellaneous/create-cashless-entry/{tollId}', function (int $tollId) {
    try {
        $toll = TollTransaction::query()->find($tollId);
        if ($toll === null) {
            return response()->json(['ok' => false, 'message' => 'Toll transaction not found.'], 404);
        }
        $accountNo = (string) ($toll->account_no);

        Log::info('Account number: ' . $accountNo);

        $account = Account::query()->where('account_no', $accountNo)->first();

        log::info('Account: ' . $account);

        if ($account === null) {
            return response()->json(['ok' => false, 'message' => 'Account not found.'], 404);
        }

        $submission = app(ErmsMiscellaneousSubmissionService::class)->submit(app(CashlessTollMiscellaneousMapper::class)->map($toll, $account));

        if ($submission['ok']) {
            TollTransaction::where('id', $tollId)->update(['erms_status' => 1]);
        } else if (! $submission['ok']) {
            TollTransaction::where('id', $tollId)->update(['erms_status' => 2]);
        }

        return response()->json($submission);
    } catch (\Throwable $e) {
        Log::error('ERMS miscellaneous cashless entry creation failed', ['toll_transaction_id' => $tollId, 'error' => $e->getMessage()]);
        return response()->json(['ok' => false, 'message' => 'Could not create ERMS miscellaneous cashless entry.'], 500);
    }
})->name('erms.miscellaneous.create-cashless-entry');

Route::get('erms/tbs-bundle/revenue-payload-preview/{subscriptionId}', function (int $subscriptionId) {
    try {
        $row = DB::table('bundle_subscriptions as bs')
            ->join('bridge_bills as bb', 'bb.id', '=', 'bs.bill_id')
            ->where('bs.id', $subscriptionId)
            ->select([
                'bs.id',
                'bs.account_id',
                'bs.bill_id',
                'bs.expire_date',
                'bs.status',
                'bs.erms_status',
                'bb.source',
                'bb.erp_status',
                'bb.paid_amt',
                'bb.receipt_number',
                'bb.dist_param',
                'bb.payer_name',
            ])
            ->first();

        if ($row === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Bundle subscription not found.',
            ], 404);
        }

        if (strtoupper((string) ($row->source ?? '')) !== 'TBS') {
            return response()->json([
                'ok' => false,
                'message' => 'Bundle subscription is not linked to a TBS bridge bill.',
                'bundle_subscription_id' => $subscriptionId,
                'bill_id' => $row->bill_id,
            ], 422);
        }

        $account = null;
        if ($row->account_id !== null && $row->account_id !== '') {
            $account = Account::query()->where('account_no', $row->account_id)->first();
        }

        $mapper = app(BundleSubscriptionMapper::class);
        $attrs = $mapper->map($row, $account);

        $payloadModel = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Traits\Erms\ErmsMiscellaneousPayloadTrait;
        };
        $payloadModel->setRawAttributes($attrs, true);
        $payloadModel->validateErmsMiscellaneousPayload();

        return response()->json([
            'ok' => true,
            'message' => 'ERMS TBS bundle revenue payload preview generated.',
            'bundle_subscription_id' => (int) $row->id,
            'bridge_bill_id' => (int) $row->bill_id,
            'account_id' => $row->account_id,
            'expire_date' => $row->expire_date,
            'bridge_bill_erp_status' => $row->erp_status,
            'subscription_erms_status' => $row->erms_status,
            'endpoint' => $payloadModel->getErmsMiscellaneousEndpoint(),
            'headers' => $payloadModel->toErmsMiscellaneousHeaders(),
            'mapped_attributes' => $attrs,
            'unsigned_data' => $payloadModel->toMiscellaneousEntryData(),
            'signed_payload' => $payloadModel->toErmsMiscellaneousPayload(),
        ]);
    } catch (\Throwable $e) {
        Log::error('ERMS TBS bundle revenue payload preview failed', [
            'bundle_subscription_id' => $subscriptionId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Could not generate ERMS TBS bundle revenue payload preview.',
            'error' => $e->getMessage(),
        ], 500);
    }
})->name('erms.tbs-bundle.revenue-payload-preview');