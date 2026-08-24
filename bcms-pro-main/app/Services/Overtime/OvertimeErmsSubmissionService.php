<?php

namespace App\Services\Overtime;

use App\Models\Bms\OvertimeBatch;
use App\Services\Erms\ErmsPayableSubmissionService;
use App\Services\Erms\Mappers\OvertimeBatchPayableMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OvertimeErmsSubmissionService
{
    public function __construct(
        private OvertimeBatchPayableMapper $overtimeBatchPayableMapper,
        private ErmsPayableSubmissionService $ermsPayableSubmissionService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function submissionStatusForBatch(OvertimeBatch $batch): array
    {
        $batch = $batch->fresh() ?? $batch;
        $ermsStatus = (int) ($batch->erms_status ?? 0);
        $alreadySent = $ermsStatus === 1 || trim((string) ($batch->payment_reference ?? '')) !== '';

        return [
            'batch_id' => (int) $batch->id,
            'batch_number' => (string) $batch->batch_number,
            'batch_status' => (string) $batch->status,
            'erms_status' => $ermsStatus,
            'payment_reference' => $batch->payment_reference,
            'payment_status' => $batch->payment_status,
            'can_repost' => strtolower((string) $batch->status) === 'approved'
                && !$alreadySent
                && $ermsStatus === 2,
        ];
    }

    /**
     * @return array{submission: array<string, mixed>|null, skipped: string|null}
     */
    public function submitOvertimeBatch(OvertimeBatch $batch): array
    {
        if (strtolower((string) $batch->status) !== 'approved') {
            return ['submission' => null, 'skipped' => 'status_not_approved'];
        }

        $ermsStatus = (int) ($batch->erms_status ?? 0);

        if ($ermsStatus === 1 || trim((string) ($batch->payment_reference ?? '')) !== '') {
            return ['submission' => null, 'skipped' => 'already_sent'];
        }

        if ($ermsStatus === 2) {
            return ['submission' => null, 'skipped' => 'failed_use_manual_repost'];
        }

        return [
            'submission' => $this->submitBatchToErms($batch),
            'skipped' => null,
        ];
    }

    /**
     * @return array{ok: bool, submission: array<string, mixed>, erms_status: int, payment_reference: string|null}
     */
    public function repostFailedSubmission(OvertimeBatch $batch): array
    {
        $batch = $batch->fresh() ?? $batch;
        $ermsStatus = (int) ($batch->erms_status ?? 0);

        if (strtolower((string) ($batch->status ?? '')) !== 'approved') {
            throw new InvalidArgumentException('Only approved overtime batches can be reposted to ERMS.');
        }

        if ($ermsStatus === 1 || trim((string) ($batch->payment_reference ?? '')) !== '') {
            throw new InvalidArgumentException('This batch was already submitted to ERMS successfully.');
        }

        if ($ermsStatus !== 2) {
            throw new InvalidArgumentException('Only batches with a failed ERMS submission can be reposted.');
        }

        $submission = $this->submitBatchToErms($batch);
        $batch = $batch->fresh() ?? $batch;

        return [
            'ok' => (bool) ($submission['ok'] ?? false),
            'submission' => $submission,
            'erms_status' => (int) ($batch->erms_status ?? 0),
            'payment_reference' => $batch->payment_reference,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function submitBatchToErms(OvertimeBatch $batch): array
    {
        return DB::connection('bcmis2')->transaction(function () use ($batch) {
            /** @var OvertimeBatch|null $locked */
            $locked = OvertimeBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new InvalidArgumentException('Overtime batch not found.');
            }

            $ermsStatus = (int) ($locked->erms_status ?? 0);
            if ($ermsStatus === 1 || trim((string) ($locked->payment_reference ?? '')) !== '') {
                Log::info('Overtime ERMS submit skipped: already sent', [
                    'batch_id' => $locked->id,
                    'batch_number' => $locked->batch_number,
                ]);

                return [
                    'ok' => true,
                    'skipped' => true,
                    'message' => 'Already submitted to ERMS.',
                ];
            }

            $unsigned = $this->overtimeBatchPayableMapper->map($locked);
            $submission = $this->ermsPayableSubmissionService->submit($unsigned, [
                'batch_number' => $locked->batch_number,
            ]);

            $locked->erms_status = !empty($submission['ok']) ? 1 : 2;
            $response = $submission['response'] ?? null;
            $requestNumber = is_array($response)
                ? data_get($response, 'data.payment.requestNumber')
                : null;

            if ($requestNumber !== null && $requestNumber !== '') {
                $locked->payment_reference = (string) $requestNumber;
            }

            $locked->save();

            Log::info('Overtime ERMS submission completed', [
                'batch_id' => $locked->id,
                'batch_number' => $locked->batch_number,
                'ok' => (bool) ($submission['ok'] ?? false),
                'erms_status' => (int) $locked->erms_status,
            ]);

            return $submission;
        });
    }
}
