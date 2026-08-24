<?php

namespace App\Services\Overtime;

use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\OvertimeRequest;
use App\Models\Bms\OvertimeRequestHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OvertimeEOfficeWebhookService
{
    private const HISTORY_ACTION = 'Approve Payment (EOffice)';

    private OvertimeQueryService $overtimeQueryService;

    public function __construct(
        OvertimeQueryService $overtimeQueryService,
    ) {
        $this->overtimeQueryService = $overtimeQueryService;
    }

    /**
     * @return array{success: true, data: array, message: string}|array{success: false, error: string, http: int}
     */
    public function handleStatusUpdate(array $attributes): array
    {
        try {
            if (!isset($attributes['documentReference'], $attributes['systemName'], $attributes['processName'], $attributes['decision'])) {
                return [
                    'success' => false,
                    'error' => 'Missing required fields: documentReference, systemName, processName, or decision',
                    'http' => 422,
                ];
            }

            $documentReference = trim($attributes['documentReference']);
            $documentReference = preg_replace('/^([A-Z]{3})(\d{8})(\d{4})$/i', '$1-$2-$3', $documentReference);
            $decision = strtolower(trim($attributes['decision']));

            $batch = OvertimeBatch::where('external_batch_id', $documentReference)->first();

            if (!$batch) {
                Log::warning('Batch not found for e-office update', [
                    'documentReference' => $documentReference,
                    'external_batch_id' => $documentReference,
                ]);

                return [
                    'success' => false,
                    'error' => 'Batch not found',
                    'http' => 404,
                ];
            }

            $internalStatus = $this->resolveInternalStatus($decision);
            $newStatus = $this->resolveDisplayStatus($decision);

            if ($this->isDuplicateEOfficeCallback($batch, $decision, $internalStatus)) {
                return $this->duplicateCallbackResponse($batch, $decision);
            }

            DB::beginTransaction();

            $this->applyEOfficeFeedback($batch, $attributes, $decision, $internalStatus, $newStatus);

            DB::commit();

            Log::info('EOffice status update processed', [
                'batch_id' => $batch->id,
                'external_batch_id' => $documentReference,
                'decision' => $decision,
                'status' => $batch->status,
                'skipped' => false,
            ]);

            return [
                'success' => true,
                'data' => [
                    'batch_id' => $batch->id,
                    'status' => $batch->status,
                    'decision' => $decision,
                    'skipped' => false,
                    'feedback_saved' => true,
                ],
                'message' => 'Status updated successfully',
            ];
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::error('Failed to process e-office status update', [
                'error' => $e->getMessage(),
                'request' => $attributes,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Failed to process status update: ' . $e->getMessage(),
                'http' => 500,
            ];
        }
    }

    private function resolveInternalStatus(string $decision): string
    {
        $statusMap = [
            'approved' => 'approved',
            'rejected' => 'rejected',
            'processing' => 'processing',
            'completed' => 'completed',
        ];

        $internalStatus = $statusMap[$decision] ?? $decision;

        $validStatuses = ['draft', 'submitted', 'approved', 'rejected', 'processing', 'completed', 'failed'];
        if (!in_array(strtolower($internalStatus), $validStatuses, true)) {
            Log::warning('Invalid status value for batch', [
                'decision' => $decision,
                'internalStatus' => $internalStatus,
                'validStatuses' => $validStatuses,
            ]);

            return 'processing';
        }

        return $internalStatus;
    }

    private function resolveDisplayStatus(string $decision): string
    {
        $displayStatusMap = [
            'approved' => 'Payment Approved',
            'rejected' => 'Payment Rejected',
            'processing' => 'Payment Processing',
            'completed' => 'Payment Completed',
        ];

        return $displayStatusMap[$decision] ?? $decision;
    }

    private function isDuplicateEOfficeCallback(OvertimeBatch $batch, string $decision, string $internalStatus): bool
    {
        $lastDecision = $this->lastEOfficeDecision($batch);
        if ($lastDecision === null) {
            return false;
        }

        return strtolower((string) $batch->status) === strtolower($internalStatus)
            && $lastDecision === $decision;
    }

    private function lastEOfficeDecision(OvertimeBatch $batch): ?string
    {
        $response = $batch->external_response;
        if (!is_array($response)) {
            return null;
        }

        $decision = strtolower(trim((string) ($response['decision'] ?? '')));

        return $decision !== '' ? $decision : null;
    }

    /**
     * @return array{success: true, data: array, message: string}
     */
    private function duplicateCallbackResponse(OvertimeBatch $batch, string $decision): array
    {
        $ermsStatus = (int) ($batch->erms_status ?? 0);
        $ermsSkipped = ($ermsStatus === 1 || trim((string) ($batch->payment_reference ?? '')) !== '')
            ? 'already_sent'
            : ($ermsStatus === 2 ? 'failed_use_manual_repost' : null);

        return [
            'success' => true,
            'data' => [
                'batch_id' => $batch->id,
                'status' => $batch->status,
                'decision' => $decision,
                'skipped' => true,
                'skip_reason' => 'duplicate_eoffice_callback',
                'feedback_saved' => false,
                'erms_payable_submission' => null,
                'erms_payable_submission_skipped' => $ermsSkipped,
            ],
            'message' => 'Duplicate e-Office callback acknowledged',
        ];
    }

    private function applyEOfficeFeedback(
        OvertimeBatch $batch,
        array $attributes,
        string $decision,
        string $internalStatus,
        string $newStatus
    ): void {
        $workflowStatus = $this->overtimeQueryService->mapToWorkflowStatus($newStatus);
        $historyComment = "Status updated from e-Office system: {$decision}";

        $batch->status = $internalStatus;
        $batch->external_response = $attributes;
        $batch->updated_at = now();

        if ($decision === 'completed' || $decision === 'approved') {
            $batch->completed_at = now();
        }

        if (!$batch->save()) {
            throw new \RuntimeException('Failed to save batch feedback data');
        }

        $batch->refresh();

        $overtimeRequestIds = $attributes['overtime_request_ids'] ?? $batch->overtimeRequests->pluck('id')->toArray();

        foreach ($overtimeRequestIds as $requestId) {
            $overtimeRequest = OvertimeRequest::find($requestId);
            if (!$overtimeRequest || $overtimeRequest->batch_id != $batch->id) {
                continue;
            }

            if ((string) $overtimeRequest->external_status === $decision
                && (string) $overtimeRequest->status === $newStatus
                && (string) $overtimeRequest->workflow_status === (string) $workflowStatus
            ) {
                continue;
            }

            $overtimeRequest->external_status = $decision;
            $overtimeRequest->status = $newStatus;
            $overtimeRequest->workflow_status = $workflowStatus;
            $overtimeRequest->external_status_updated_at = now();

            if (!$overtimeRequest->save()) {
                Log::error('Failed to save overtime request status', [
                    'request_id' => $requestId,
                    'batch_id' => $batch->id,
                ]);
                continue;
            }

            if ($this->historyExists($overtimeRequest->id, $newStatus, $historyComment)) {
                continue;
            }

            try {
                OvertimeRequestHistory::create([
                    'overtime_request_id' => $overtimeRequest->id,
                    'action' => self::HISTORY_ACTION,
                    'status' => $newStatus,
                    'workflow_status' => $workflowStatus,
                    'performed_by' => 'System',
                    'performed_by_role' => 'e-office System',
                    'comment' => $historyComment,
                ]);
            } catch (\Exception $historyException) {
                Log::error('Failed to create overtime request history', [
                    'request_id' => $requestId,
                    'error' => $historyException->getMessage(),
                ]);
            }
        }
    }

    private function historyExists(int $overtimeRequestId, string $status, string $comment): bool
    {
        return OvertimeRequestHistory::query()
            ->where('overtime_request_id', $overtimeRequestId)
            ->where('action', self::HISTORY_ACTION)
            ->where('status', $status)
            ->where('comment', $comment)
            ->exists();
    }
}
