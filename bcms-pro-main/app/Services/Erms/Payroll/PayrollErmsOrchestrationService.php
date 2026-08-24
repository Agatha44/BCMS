<?php

namespace App\Services\Erms\Payroll;

use App\Models\Bms\Payroll\PayrollErmsExecution;
use App\Models\Bms\Payroll\PayrollRun;
use App\Services\Erms\ErmsMiscellaneousSubmissionService;
use App\Services\Erms\ErmsPayableSubmissionService;
use App\Services\Erms\Mappers\PayrollDeductionPayableMapper;
use App\Services\Erms\Mappers\PayrollNetPayMapper;
use App\Services\Erms\Mappers\PayrollRunMiscellaneousMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Runs the split payroll ERMS flow after a run is posted:
 * miscellaneous → net-pay by bank → deduction payables (PAYE, PSSSF, HESLB, …).
 */
class PayrollErmsOrchestrationService
{
    public function __construct(
        private PayrollRunMiscellaneousMapper $miscMapper,
        private PayrollNetPayMapper $netPayMapper,
        private PayrollDeductionPayableMapper $deductionPayableMapper,
        private ErmsMiscellaneousSubmissionService $miscSubmissionService,
        private ErmsPayableSubmissionService $payableSubmissionService,
        private PayrollErmsExecutionService $executionService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function submitPostedRun(PayrollRun $run, array $context = []): array
    {
        $run = $run->fresh() ?? $run;

        if (strtolower((string) ($run->status ?? '')) !== 'posted') {
            throw new InvalidArgumentException('Payroll run must be posted before ERMS submission.');
        }

        $logContext = [
            'payroll_run_id' => $run->id,
            'payroll_number' => $run->payroll_number,
        ];

        $misc = $this->submitMiscellaneous($run, $context, $logContext);

        $netPay = $this->skippedPayableGroup('buckets');
        $deductions = $this->skippedPayableGroup('payables');

        if ($misc['ok']) {
            $netPay = $this->submitAllNetPayBuckets($run, $context, $logContext);
            $deductions = $this->submitAllDeductionPayables($run, $context, $logContext);
        } else {
            Log::warning('Payroll ERMS: skipping payables after miscellaneous failure', $logContext);
        }

        $overallOk = $misc['ok']
            && ($netPay['ok'] ?? false)
            && ($deductions['ok'] ?? false);

        $primaryReference = $misc['erms_reference']
            ?? $this->firstPayableReference($netPay)
            ?? $this->firstPayableReference($deductions);

        $this->updateRunErmsFields($run, $overallOk, $primaryReference);

        return [
            'ok' => $overallOk,
            'misc' => $misc,
            'net_pay' => $netPay,
            'deduction_payables' => $deductions,
            'erms_reference' => $primaryReference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function executionStatusForRun(PayrollRun $run): array
    {
        $run = $run->fresh() ?? $run;
        $executions = $this->loadExecutions($run);

        $failed = $executions->where('status', PayrollErmsExecution::STATUS_FAILED);
        $miscOk = $this->miscellaneousIsSuccessful($run);

        return [
            'payroll_run_id' => (int) $run->id,
            'erms_status' => (int) $run->erms_status,
            'miscellaneous_ok' => $miscOk,
            'can_repost' => $this->canRepost($run),
            'total_executions' => $executions->count(),
            'failed_count' => $failed->count(),
            'executions' => $executions
                ->sortBy(fn (PayrollErmsExecution $e) => $this->executionRetryPriority($e))
                ->values()
                ->map(fn (PayrollErmsExecution $e) => $this->formatExecution($e))
                ->all(),
        ];
    }

    /**
     * Re-submit failed batches, then submit any payables that were never attempted
     * once miscellaneous has succeeded.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function retryFailedExecutions(PayrollRun $run, array $context = []): array
    {
        $run = $run->fresh() ?? $run;

        if (strtolower((string) ($run->status ?? '')) !== 'posted') {
            throw new InvalidArgumentException('Payroll run must be posted before reposting to ERMS.');
        }

        $logContext = [
            'payroll_run_id' => $run->id,
            'payroll_number' => $run->payroll_number,
        ];

        $failed = $this->loadExecutions($run)
            ->where('status', PayrollErmsExecution::STATUS_FAILED)
            ->sortBy(fn (PayrollErmsExecution $e) => $this->executionRetryPriority($e))
            ->values();

        $retried = [];

        if ($failed->isEmpty() && (int) ($run->erms_status ?? 0) === 2 && ! $this->miscellaneousIsSuccessful($run)) {
            Log::info('Payroll ERMS repost: no failed execution rows; retrying miscellaneous', $logContext);
            $retried[] = array_merge(
                ['execution_type' => PayrollErmsExecution::TYPE_MISCELLANEOUS, 'bank_batch' => ''],
                $this->submitMiscellaneous($run, $context, $logContext),
            );
        }

        foreach ($failed as $execution) {
            $alreadyPosted = $this->skipIfExecutionAlreadyPosted($execution);
            if ($alreadyPosted !== null) {
                $retried[] = $alreadyPosted;

                continue;
            }

            if ($this->executionDependsOnMiscellaneous($execution) && ! $this->miscellaneousIsSuccessful($run)) {
                $retried[] = [
                    'erms_execution_id' => $execution->id,
                    'execution_type' => $execution->execution_type,
                    'bank_batch' => $execution->bank_batch,
                    'label' => $this->executionLabel($execution),
                    'ok' => false,
                    'skipped' => true,
                    'error' => 'Miscellaneous journal must be posted successfully before this batch can be reposted.',
                ];

                continue;
            }

            $retried[] = $this->retryExecution($run, $execution, $context, $logContext);
        }

        $netPay = null;
        $deductions = null;

        $run = $run->fresh() ?? $run;
        if ($this->miscellaneousIsSuccessful($run)) {
            $pending = $this->submitMissingPayables($run, $context, $logContext);
            $netPay = $pending['net_pay'];
            $deductions = $pending['deduction_payables'];

            foreach ($pending['retried_items'] as $item) {
                $retried[] = $item;
            }
        }

        $this->reconcileRunErmsStatus($run);

        $run = $run->fresh() ?? $run;
        $stillFailed = $this->loadExecutions($run)
            ->where('status', PayrollErmsExecution::STATUS_FAILED)
            ->count();

        $overallOk = $stillFailed === 0
            && $this->miscellaneousIsSuccessful($run)
            && $this->payablesAreComplete($run);

        return [
            'ok' => $overallOk,
            'retried_count' => count($retried),
            'still_failed_count' => $stillFailed,
            'erms_status' => (int) ($run->erms_status ?? 0),
            'erms_reference' => $run->erms_reference,
            'retried' => $retried,
            'net_pay' => $netPay,
            'deduction_payables' => $deductions,
            'executions' => $this->executionStatusForRun($run)['executions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>|null  Skip payload when status is already success (1).
     */
    private function skipIfExecutionAlreadyPosted(PayrollErmsExecution $execution): ?array
    {
        $execution->refresh();

        if ((int) $execution->status !== PayrollErmsExecution::STATUS_SUCCESS) {
            return null;
        }

        Log::info('Payroll ERMS repost skipped: batch already posted', [
            'erms_execution_id' => $execution->id,
            'execution_type' => $execution->execution_type,
            'bank_batch' => $execution->bank_batch,
            'erms_reference' => $execution->erms_reference,
        ]);

        return [
            'erms_execution_id' => $execution->id,
            'execution_type' => $execution->execution_type,
            'bank_batch' => $execution->bank_batch,
            'label' => $this->executionLabel($execution),
            'ok' => true,
            'skipped' => true,
            'message' => 'Already posted to ERMS successfully; repost skipped.',
            'erms_reference' => $execution->erms_reference,
            'erms_execution_status' => (int) $execution->status,
        ];
    }

    private function retryExecution(
        PayrollRun $run,
        PayrollErmsExecution $execution,
        array $context,
        array $logContext,
    ): array {
        Log::info('Payroll ERMS repost batch', array_merge($logContext, [
            'erms_execution_id' => $execution->id,
            'execution_type' => $execution->execution_type,
            'bank_batch' => $execution->bank_batch,
        ]));

        $base = [
            'erms_execution_id' => $execution->id,
            'execution_type' => $execution->execution_type,
            'bank_batch' => $execution->bank_batch,
            'label' => $this->executionLabel($execution),
            'skipped' => false,
        ];

        try {
            if ($execution->execution_type === PayrollErmsExecution::TYPE_MISCELLANEOUS) {
                return array_merge($base, $this->submitMiscellaneous($run, $context, $logContext));
            }

            if ($execution->execution_type === PayrollErmsExecution::TYPE_NET_PAY_PAYABLE) {
                return array_merge($base, $this->retryNetPayBucket($run, (string) $execution->bank_batch, $context, $logContext));
            }

            if (str_starts_with($execution->execution_type, PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE.':')) {
                if (! $this->deductionPayablesExecutionEnabled()) {
                    return array_merge($base, [
                        'ok' => true,
                        'skipped' => true,
                        'message' => 'Deduction payables execution is disabled.',
                    ]);
                }

                $kind = substr($execution->execution_type, strlen(PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE) + 1);

                return array_merge($base, $this->retryDeductionKind($run, $kind, $context, $logContext));
            }

            if ($execution->execution_type === PayrollErmsExecution::TYPE_PAYABLE) {
                return array_merge($base, [
                    'ok' => false,
                    'error' => 'Legacy monolithic payable (PayrollRunPayableMapper) is retired. '
                        .'Repost is only supported for miscellaneous, net-pay bank batches, and deduction payables.',
                ]);
            }

            return array_merge($base, [
                'ok' => false,
                'error' => 'Unsupported execution type for repost: '.$execution->execution_type,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payroll ERMS repost batch failed', array_merge($logContext, [
                'erms_execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]));

            return array_merge($base, [
                'ok' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function retryNetPayBucket(PayrollRun $run, string $bucketKey, array $context, array $logContext): array
    {
        try {
            $unsigned = $this->netPayMapper->mapBucket($run, $bucketKey, $context);
        } catch (InvalidArgumentException $e) {
            if (str_contains(strtolower($e->getMessage()), 'no valid')) {
                return [
                    'ok' => false,
                    'error' => "Net-pay batch \"{$bucketKey}\" has no payload for this run (zero amount or skipped).",
                ];
            }

            throw $e;
        }

        return $this->submitOnePayable(
            $run,
            $unsigned,
            $logContext,
            PayrollErmsExecution::TYPE_NET_PAY_PAYABLE,
            $bucketKey,
            ['bank_bucket' => $bucketKey],
            'Payroll net-pay bucket reposted to ERMS',
            [
                'bucket' => $bucketKey,
                'label' => $this->netPayMapper->bankLabel($bucketKey),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function retryDeductionKind(PayrollRun $run, string $kind, array $context, array $logContext): array
    {
        $kind = strtolower(trim($kind));

        try {
            $unsigned = $this->deductionPayableMapper->map($run, $kind, $context);
        } catch (InvalidArgumentException $e) {
            if (str_contains(strtolower($e->getMessage()), 'no ')) {
                return [
                    'ok' => false,
                    'error' => "Deduction payable \"{$kind}\" has no payload for this run (zero amount or skipped).",
                ];
            }

            throw $e;
        }

        return $this->submitOnePayable(
            $run,
            $unsigned,
            $logContext,
            PayrollDeductionPayableMapper::executionType($kind),
            (string) ($unsigned['deduction_kind'] ?? $kind),
            ['deduction_kind' => $kind],
            'Payroll deduction payable reposted to ERMS',
            [
                'kind' => $kind,
                'label' => $unsigned['deduction_label'] ?? $kind,
            ],
        );
    }

    private function canRepost(PayrollRun $run): bool
    {
        if (strtolower((string) ($run->status ?? '')) !== 'posted') {
            return false;
        }

        if ((int) ($run->erms_status ?? 0) === 2) {
            return true;
        }

        if ($this->miscellaneousIsSuccessful($run) && ! $this->payablesAreComplete($run)) {
            return true;
        }

        return $this->loadExecutions($run)
            ->contains('status', PayrollErmsExecution::STATUS_FAILED);
    }

    private function miscellaneousIsSuccessful(PayrollRun $run): bool
    {
        return $this->loadExecutions($run)
            ->contains(fn (PayrollErmsExecution $e) => $e->execution_type === PayrollErmsExecution::TYPE_MISCELLANEOUS
                && (int) $e->status === PayrollErmsExecution::STATUS_SUCCESS);
    }

    private function executionDependsOnMiscellaneous(PayrollErmsExecution $execution): bool
    {
        return $execution->execution_type !== PayrollErmsExecution::TYPE_MISCELLANEOUS;
    }

    /**
     * @return Collection<int, PayrollErmsExecution>
     */
    private function loadExecutions(PayrollRun $run): Collection
    {
        return PayrollErmsExecution::query()
            ->where('payroll_run_id', $run->id)
            ->orderBy('id')
            ->get();
    }

    private function executionRetryPriority(PayrollErmsExecution $execution): int
    {
        return match ($execution->execution_type) {
            PayrollErmsExecution::TYPE_MISCELLANEOUS => 0,
            PayrollErmsExecution::TYPE_NET_PAY_PAYABLE => 10,
            PayrollErmsExecution::TYPE_PAYABLE => 15,
            default => str_starts_with((string) $execution->execution_type, PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE.':')
                ? 20
                : 99,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatExecution(PayrollErmsExecution $execution): array
    {
        return [
            'id' => (int) $execution->id,
            'execution_type' => (string) $execution->execution_type,
            'bank_batch' => (string) $execution->bank_batch,
            'label' => $this->executionLabel($execution),
            'source_ref' => (string) $execution->source_ref,
            'status' => (int) $execution->status,
            'status_label' => $this->statusLabel((int) $execution->status),
            'erms_reference' => $execution->erms_reference,
            'http_status' => $execution->http_status,
            'error_message' => $execution->error_message,
            'response_payload' => $execution->response_payload,
            'submitted_at' => $execution->submitted_at?->toIso8601String(),
        ];
    }

    private function executionLabel(PayrollErmsExecution $execution): string
    {
        return match ($execution->execution_type) {
            PayrollErmsExecution::TYPE_MISCELLANEOUS => 'Miscellaneous journal',
            PayrollErmsExecution::TYPE_NET_PAY_PAYABLE => 'Net pay: '.strtoupper((string) ($execution->bank_batch ?: 'batch')),
            PayrollErmsExecution::TYPE_PAYABLE => 'Payroll payable',
            default => str_starts_with((string) $execution->execution_type, PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE.':')
                ? 'Deduction: '.strtoupper(substr($execution->execution_type, strlen(PayrollErmsExecution::TYPE_DEDUCTION_PAYABLE) + 1))
                : $execution->execution_type,
        };
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            PayrollErmsExecution::STATUS_SUCCESS => 'success',
            PayrollErmsExecution::STATUS_FAILED => 'failed',
            default => 'pending',
        };
    }

    private function reconcileRunErmsStatus(PayrollRun $run): void
    {
        $executions = $this->loadExecutions($run);
        $anyFailed = $executions->contains('status', PayrollErmsExecution::STATUS_FAILED);
        $miscOk = $this->miscellaneousIsSuccessful($run);

        $overallOk = $miscOk && ! $anyFailed && $this->payablesAreComplete($run);

        $primaryReference = $executions
            ->first(fn (PayrollErmsExecution $e) => $e->execution_type === PayrollErmsExecution::TYPE_MISCELLANEOUS
                && (int) $e->status === PayrollErmsExecution::STATUS_SUCCESS)
            ?->erms_reference;

        if ($primaryReference === null || $primaryReference === '') {
            $primaryReference = $executions
                ->first(fn (PayrollErmsExecution $e) => (int) $e->status === PayrollErmsExecution::STATUS_SUCCESS
                    && ! empty($e->erms_reference))
                ?->erms_reference;
        }

        $this->updateRunErmsFields($run, $overallOk, $primaryReference ?: null);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function submitMiscellaneous(PayrollRun $run, array $context, array $logContext): array
    {
        try {
            $miscAttributes = $this->miscMapper->map($run, $context);
            $sourceRef = (string) ($miscAttributes['source_ref'] ?? $run->payroll_number.'-MISC');

            $submission = $this->miscSubmissionService->submit($miscAttributes);
            $execution = $this->executionService->record(
                $run,
                PayrollErmsExecution::TYPE_MISCELLANEOUS,
                $sourceRef,
                $submission,
            );

            Log::info('Payroll miscellaneous submitted to ERMS (orchestration)', array_merge($logContext, [
                'source_ref' => $sourceRef,
                'submission_ok' => $submission['ok'] ?? false,
                'erms_execution_id' => $execution->id,
            ]));

            return [
                'ok' => (bool) ($submission['ok'] ?? false),
                'skipped' => false,
                'source_ref' => $sourceRef,
                'erms_execution_id' => $execution->id,
                'erms_execution_status' => $execution->status,
                'erms_reference' => $execution->erms_reference,
                'http_status' => $submission['http_status'] ?? null,
                'error' => $submission['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('Payroll ERMS miscellaneous failed (orchestration)', array_merge($logContext, [
                'error' => $e->getMessage(),
            ]));

            $sourceRef = (string) ($run->payroll_number ?? '').'-MISC';
            $execution = $this->executionService->record(
                $run,
                PayrollErmsExecution::TYPE_MISCELLANEOUS,
                $sourceRef,
                [
                    'ok' => false,
                    'error' => $e->getMessage(),
                    'http_status' => null,
                    'response' => null,
                ],
            );

            return [
                'ok' => false,
                'skipped' => false,
                'source_ref' => $sourceRef,
                'erms_execution_id' => $execution->id,
                'erms_execution_status' => $execution->status,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit net-pay and deduction payables that have no successful execution yet.
     * Used after miscellaneous succeeds on repost when payables were skipped initially.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array{net_pay: array<string, mixed>|null, deduction_payables: array<string, mixed>|null, retried_items: list<array<string, mixed>>}
     */
    private function submitMissingPayables(PayrollRun $run, array $context, array $logContext): array
    {
        $netPayItems = [];
        $deductionItems = [];
        $retriedItems = [];

        foreach ($this->netPayMapper->bucketKeys() as $bucketKey) {
            try {
                $unsigned = $this->netPayMapper->mapBucket($run, $bucketKey, $context);
            } catch (InvalidArgumentException $e) {
                if (str_contains(strtolower($e->getMessage()), 'no valid')) {
                    continue;
                }

                throw $e;
            }

            $bankBatch = $bucketKey;
            if ($this->hasSuccessfulExecution($run, PayrollErmsExecution::TYPE_NET_PAY_PAYABLE, $bankBatch)) {
                continue;
            }

            Log::info('Payroll ERMS repost: submitting missing net-pay bucket', array_merge($logContext, [
                'bank_bucket' => $bankBatch,
            ]));

            $result = $this->submitOnePayable(
                $run,
                $unsigned,
                $logContext,
                PayrollErmsExecution::TYPE_NET_PAY_PAYABLE,
                $bankBatch,
                ['bank_bucket' => $bankBatch],
                'Payroll net-pay bucket submitted to ERMS (missing on repost)',
                [
                    'bucket' => $bankBatch,
                    'label' => $this->netPayMapper->bankLabel($bucketKey),
                ],
            );

            $netPayItems[] = $result;
            $retriedItems[] = array_merge([
                'execution_type' => PayrollErmsExecution::TYPE_NET_PAY_PAYABLE,
                'bank_batch' => $bankBatch,
                'label' => 'Net pay: '.strtoupper($bankBatch),
                'skipped' => false,
            ], $result);
        }

        if ($this->deductionPayablesExecutionEnabled()) {
            foreach ($this->deductionPayableMapper->kindsWithAmounts($run) as $kind) {
                $executionType = PayrollDeductionPayableMapper::executionType($kind);
                $unsigned = $this->deductionPayableMapper->map($run, $kind, $context);
                $bankBatch = (string) ($unsigned['deduction_kind'] ?? $kind);

                if ($this->hasSuccessfulExecution($run, $executionType, $bankBatch)) {
                    continue;
                }

                Log::info('Payroll ERMS repost: submitting missing deduction payable', array_merge($logContext, [
                    'deduction_kind' => $bankBatch,
                ]));

                $result = $this->submitOnePayable(
                    $run,
                    $unsigned,
                    $logContext,
                    $executionType,
                    $bankBatch,
                    ['deduction_kind' => $bankBatch],
                    'Payroll deduction payable submitted to ERMS (missing on repost)',
                    [
                        'kind' => $bankBatch,
                        'label' => $unsigned['deduction_label'] ?? $bankBatch,
                        'deduction_amounts' => $unsigned['deduction_amounts'] ?? null,
                    ],
                );

                $deductionItems[] = $result;
                $retriedItems[] = array_merge([
                    'execution_type' => $executionType,
                    'bank_batch' => $bankBatch,
                    'label' => 'Deduction: '.strtoupper($bankBatch),
                    'skipped' => false,
                ], $result);
            }
        }

        return [
            'net_pay' => $this->summarizePayableSubmissionGroup($netPayItems, 'buckets'),
            'deduction_payables' => $this->deductionPayablesExecutionEnabled()
                ? $this->summarizePayableSubmissionGroup($deductionItems, 'payables')
                : [
                    'ok' => true,
                    'skipped' => true,
                    'payables' => [],
                    'submitted_count' => 0,
                    'failed_count' => 0,
                    'message' => 'Deduction payables execution is disabled.',
                ],
            'retried_items' => $retriedItems,
        ];
    }

    private function hasSuccessfulExecution(PayrollRun $run, string $executionType, string $bankBatch = ''): bool
    {
        return $this->loadExecutions($run)->contains(
            fn (PayrollErmsExecution $execution) => $execution->execution_type === $executionType
                && (string) $execution->bank_batch === $bankBatch
                && (int) $execution->status === PayrollErmsExecution::STATUS_SUCCESS,
        );
    }

    private function payablesAreComplete(PayrollRun $run): bool
    {
        if (! $this->miscellaneousIsSuccessful($run)) {
            return false;
        }

        foreach ($this->netPayMapper->bucketKeys() as $bucketKey) {
            try {
                $unsigned = $this->netPayMapper->mapBucket($run, $bucketKey, []);
            } catch (InvalidArgumentException $e) {
                if (str_contains(strtolower($e->getMessage()), 'no valid')) {
                    continue;
                }

                return false;
            }

            if (! $this->hasSuccessfulExecution($run, PayrollErmsExecution::TYPE_NET_PAY_PAYABLE, $bucketKey)) {
                return false;
            }
        }

        if ($this->deductionPayablesExecutionEnabled()) {
            foreach ($this->deductionPayableMapper->kindsWithAmounts($run) as $kind) {
                $executionType = PayrollDeductionPayableMapper::executionType($kind);

                try {
                    $unsigned = $this->deductionPayableMapper->map($run, $kind, []);
                } catch (InvalidArgumentException $e) {
                    return false;
                }

                $bankBatch = (string) ($unsigned['deduction_kind'] ?? $kind);
                if (! $this->hasSuccessfulExecution($run, $executionType, $bankBatch)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    private function summarizePayableSubmissionGroup(array $items, string $itemsKey): ?array
    {
        if ($items === []) {
            return null;
        }

        $submitted = 0;
        $failed = 0;
        foreach ($items as $item) {
            ($item['ok'] ?? false) ? $submitted++ : $failed++;
        }

        return [
            'ok' => $failed === 0,
            'skipped' => false,
            $itemsKey => $items,
            'submitted_count' => $submitted,
            'failed_count' => $failed,
        ];
    }

    /**
     * Map and post one net-pay bank bucket at a time (same pattern as repost).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function submitAllNetPayBuckets(PayrollRun $run, array $context, array $logContext): array
    {
        $items = [];
        $submitted = 0;
        $failed = 0;

        foreach ($this->netPayMapper->bucketKeys() as $bucketKey) {
            try {
                $unsigned = $this->netPayMapper->mapBucket($run, $bucketKey, $context);
            } catch (InvalidArgumentException $e) {
                if (str_contains(strtolower($e->getMessage()), 'no valid')) {
                    continue;
                }

                Log::error('Payroll ERMS net-pay mapping failed', array_merge($logContext, [
                    'bank_bucket' => $bucketKey,
                    'error' => $e->getMessage(),
                ]));

                return [
                    'ok' => false,
                    'skipped' => false,
                    'buckets' => $items,
                    'submitted_count' => $submitted,
                    'failed_count' => $failed,
                    'error' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                Log::error('Payroll ERMS net-pay mapping failed', array_merge($logContext, [
                    'bank_bucket' => $bucketKey,
                    'error' => $e->getMessage(),
                ]));

                return [
                    'ok' => false,
                    'skipped' => false,
                    'buckets' => $items,
                    'submitted_count' => $submitted,
                    'failed_count' => $failed,
                    'error' => $e->getMessage(),
                ];
            }

            $result = $this->submitOnePayable(
                $run,
                $unsigned,
                $logContext,
                PayrollErmsExecution::TYPE_NET_PAY_PAYABLE,
                $bucketKey,
                ['bank_bucket' => $bucketKey],
                'Payroll net-pay bucket submitted to ERMS',
                [
                    'bucket' => $bucketKey,
                    'label' => $this->netPayMapper->bankLabel($bucketKey),
                ],
            );

            $items[] = $result;
            ($result['ok'] ?? false) ? $submitted++ : $failed++;
        }

        if ($items === []) {
            return [
                'ok' => true,
                'skipped' => false,
                'buckets' => [],
                'submitted_count' => 0,
                'failed_count' => 0,
                'message' => 'No net-pay bank buckets with amounts to submit.',
            ];
        }

        return [
            'ok' => $failed === 0,
            'skipped' => false,
            'buckets' => $items,
            'submitted_count' => $submitted,
            'failed_count' => $failed,
        ];
    }

    /**
     * Map and post one deduction payable at a time (same pattern as repost).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function submitAllDeductionPayables(PayrollRun $run, array $context, array $logContext): array
    {
        if (! $this->deductionPayablesExecutionEnabled()) {
            Log::info('Payroll ERMS: deduction payables execution disabled; skipping', $logContext);

            return [
                'ok' => true,
                'skipped' => true,
                'payables' => [],
                'submitted_count' => 0,
                'failed_count' => 0,
                'message' => 'Deduction payables execution is disabled.',
            ];
        }

        $items = [];
        $submitted = 0;
        $failed = 0;

        foreach ($this->deductionPayableMapper->kindsWithAmounts($run) as $kind) {
            try {
                $unsigned = $this->deductionPayableMapper->map($run, $kind, $context);
            } catch (InvalidArgumentException $e) {
                Log::error('Payroll ERMS deduction payable mapping failed', array_merge($logContext, [
                    'deduction_kind' => $kind,
                    'error' => $e->getMessage(),
                ]));

                return [
                    'ok' => false,
                    'skipped' => false,
                    'payables' => $items,
                    'submitted_count' => $submitted,
                    'failed_count' => $failed,
                    'error' => $e->getMessage(),
                ];
            } catch (\Throwable $e) {
                Log::error('Payroll ERMS deduction payable mapping failed', array_merge($logContext, [
                    'deduction_kind' => $kind,
                    'error' => $e->getMessage(),
                ]));

                return [
                    'ok' => false,
                    'skipped' => false,
                    'payables' => $items,
                    'submitted_count' => $submitted,
                    'failed_count' => $failed,
                    'error' => $e->getMessage(),
                ];
            }

            $result = $this->submitOnePayable(
                $run,
                $unsigned,
                $logContext,
                PayrollDeductionPayableMapper::executionType($kind),
                (string) ($unsigned['deduction_kind'] ?? $kind),
                ['deduction_kind' => $unsigned['deduction_kind'] ?? $kind],
                'Payroll deduction payable submitted to ERMS (orchestration)',
                [
                    'kind' => $unsigned['deduction_kind'] ?? $kind,
                    'label' => $unsigned['deduction_label'] ?? $kind,
                    'deduction_amounts' => $unsigned['deduction_amounts'] ?? null,
                ],
            );

            $items[] = $result;
            ($result['ok'] ?? false) ? $submitted++ : $failed++;
        }

        if ($items === []) {
            return [
                'ok' => true,
                'skipped' => false,
                'payables' => [],
                'submitted_count' => 0,
                'failed_count' => 0,
                'message' => 'No deduction payables with amounts to submit.',
            ];
        }

        return [
            'ok' => $failed === 0,
            'skipped' => false,
            'payables' => $items,
            'submitted_count' => $submitted,
            'failed_count' => $failed,
        ];
    }

    private function deductionPayablesExecutionEnabled(): bool
    {
        return (bool) config('erms.payroll_deduction_payables_execution_enabled');
    }

    /**
     * @param  array<string, mixed>  $unsigned
     * @param  array<string, mixed>  $logContext
     * @param  array<string, mixed>  $submissionContext
     * @param  array<string, mixed>  $resultExtras
     * @return array<string, mixed>
     */
    private function submitOnePayable(
        PayrollRun $run,
        array $unsigned,
        array $logContext,
        string $executionType,
        string $bankBatch,
        array $submissionContext,
        string $logMessage,
        array $resultExtras,
    ): array {
        $submission = $this->payableSubmissionService->submit($unsigned, array_merge($logContext, $submissionContext, [
            'source_ref' => $unsigned['sourceRef'] ?? null,
        ]));

        $execution = $this->executionService->record(
            $run,
            $executionType,
            (string) ($unsigned['sourceRef'] ?? ''),
            $submission,
            $bankBatch,
        );

        Log::info($logMessage, array_merge($logContext, $submissionContext, [
            'source_ref' => $unsigned['sourceRef'] ?? null,
            'submission_ok' => $submission['ok'] ?? false,
            'erms_execution_id' => $execution->id,
            'response' => $submission['response'] ?? null,
        ]));

        return array_merge($resultExtras, [
            'source_ref' => $unsigned['sourceRef'] ?? null,
            'ok' => (bool) ($submission['ok'] ?? false),
            'erms_execution_id' => $execution->id,
            'erms_execution_status' => $execution->status,
            'erms_reference' => $execution->erms_reference,
            'amount' => $unsigned['amount'] ?? null,
            'error' => $submission['error'] ?? null,
        ]);
    }

    /** @return array<string, mixed> */
    private function skippedPayableGroup(string $itemsKey): array
    {
        return [
            'ok' => false,
            'skipped' => true,
            $itemsKey => [],
            'submitted_count' => 0,
            'failed_count' => 0,
        ];
    }

    private function updateRunErmsFields(PayrollRun $run, bool $overallOk, ?string $primaryReference): void
    {
        $run->update([
            'erms_status' => $overallOk ? 1 : 2,
            'erms_submitted_at' => now(),
            'erms_reference' => $primaryReference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $section
     */
    private function firstPayableReference(array $section): ?string
    {
        foreach ($section['buckets'] ?? $section['payables'] ?? [] as $row) {
            if (! empty($row['erms_reference'])) {
                return (string) $row['erms_reference'];
            }
        }

        return null;
    }
}
