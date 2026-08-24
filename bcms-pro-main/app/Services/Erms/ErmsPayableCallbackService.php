<?php

namespace App\Services\Erms;

use App\Models\Bms\OvertimeBatch;
use App\Models\Bms\Payroll\PayrollErmsExecution;
use App\Models\Bms\Payroll\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ErmsPayableCallbackService
{
    public function normalizeFromRequest(Request $request): array
    {
        $query = $request->query();
        $body = $request->all();

        if (isset($body['payload']) && is_array($body['payload'])) {
            $body = $body['payload'];
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $payment = is_array($data['payment'] ?? null) ? $data['payment'] : [];

        $pick = static function (string $camel, string $snake, array ...$sources): string {
            foreach ($sources as $source) {
                if (isset($source[$camel]) && $source[$camel] !== null && $source[$camel] !== '') {
                    return trim((string) $source[$camel]);
                }
                if (isset($source[$snake]) && $source[$snake] !== null && $source[$snake] !== '') {
                    return trim((string) $source[$snake]);
                }
            }

            return '';
        };

        $sources = [$payment, $query, $data];
        $requestNumber = $pick('requestNumber', 'request_number', ...$sources);
        $voucherNumber = $pick('voucherNumber', 'voucher_number', ...$sources);
        $chequeNumber = $pick('chequeNumber', 'cheque_number', ...$sources);

        if ($requestNumber === '') {
            $requestNumber = $voucherNumber !== '' ? $voucherNumber : $chequeNumber;
        }

        return [
            'source_ref' => $pick('sourceRef', 'source_ref', ...$sources),
            'payment_status' => strtoupper($pick('paymentStatus', 'payment_status', ...$sources)),
            'approval_status' => strtoupper($pick('approvalStatus', 'approval_status', ...$sources)),
            'event_type' => strtoupper($pick('eventType', 'event_type', ...$sources)),
            'is_final_stage' => $this->toBool($pick('isFinalStage', 'is_final_stage', ...$sources)),
            'is_approved' => $this->toBool($pick('isApproved', 'is_approved', ...$sources)),
            'request_number' => $requestNumber,
            'voucher_number' => $voucherNumber,
            'cheque_number' => $chequeNumber,
            'erms_reference' => trim((string) ($data['ermsReference'] ?? $data['erms_reference'] ?? $query['ermsReference'] ?? $query['erms_reference'] ?? '')),
            'stage' => $pick('stage', 'stage', ...$sources),
            'stage_note' => $pick('stageNote', 'stage_note', ...$sources),
            'message' => trim((string) ($data['message'] ?? $query['message'] ?? '')),
            'timestamp' => $pick('timestamp', 'timestamp', ...$sources),
            'amount' => $pick('amount', 'amount', ...$sources),
        ];
    }

    /** @param array<string, mixed> $normalized */
    public function handleErmsPayableCallback(array $normalized): array
    {
        $snapshot = $this->buildSnapshot($normalized);
        $sourceRef = (string) $snapshot['source_ref'];
        $requestNumber = (string) $snapshot['request_number'];
        $ermsReference = (string) $snapshot['erms_reference'];

        if ($sourceRef === '' && $requestNumber === '' && $ermsReference === '') {
            return $this->result(false, null, null, 'Missing identifiers (need at least one of sourceRef, requestNumber, or ermsReference).', $snapshot);
        }

        $batch = $this->findOvertimeBatch($normalized, $sourceRef);
        if ($batch !== null) {
            return $this->result(true, 'overtime_batch', $this->applyToOvertimeBatch($batch, $snapshot, $this->resolveStatus($normalized)), 'ERMS payable callback updated.', $snapshot);
        }

        [$run, $execution] = $this->findPayrollRun($sourceRef, $requestNumber, $ermsReference);
        if ($run !== null) {
            return $this->result(true, 'payroll_run', $this->applyToPayrollRun($run, $snapshot, $this->resolveStatus($normalized), $execution), 'Webhook update applied.', $snapshot);
        }

        Log::warning('ERMS payable webhook received but no target matched', [
            'request_number' => $requestNumber,
            'erms_reference' => $ermsReference,
            'source_ref' => $sourceRef,
        ]);

        return $this->result(false, null, null, 'No payable record matched this webhook update.', $snapshot);
    }

    /** @param array<string, mixed> $normalized */
    private function buildSnapshot(array $normalized): array
    {
        return array_merge($normalized, [
            'received_at' => now()->toIso8601String(),
            'is_fully_paid' => $this->isFullyPaid($normalized),
        ]);
    }

    /** @param array<string, mixed> $normalized */
    private function resolveStatus(array $normalized): ?string
    {
        if ($this->isFullyPaid($normalized)) {
            return 'PAID';
        }

        foreach (['payment_status', 'approval_status', 'event_type'] as $key) {
            $value = strtoupper(trim((string) ($normalized[$key] ?? '')));
            if ($value !== '' && $value !== 'PAID') {
                return $value;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $normalized */
    private function isFullyPaid(array $normalized): bool
    {
        return strtoupper((string) ($normalized['event_type'] ?? '')) === 'PAID'
            && ($normalized['is_final_stage'] ?? false)
            && ($normalized['is_approved'] ?? false);
    }

    /** @param array<string, mixed> $normalized */
    private function findOvertimeBatch(array $normalized, string $sourceRef): ?OvertimeBatch
    {
        foreach (array_unique(array_filter([
            $normalized['request_number'] ?? '',
            $normalized['voucher_number'] ?? '',
            $normalized['cheque_number'] ?? '',
        ])) as $reference) {
            $batch = OvertimeBatch::query()->where('payment_reference', $reference)->first();
            if ($batch !== null) {
                return $batch;
            }
        }

        if ($sourceRef !== '') {
            return OvertimeBatch::query()->where('batch_number', $sourceRef)->first();
        }

        return null;
    }

    /** @return array{0: PayrollRun|null, 1: PayrollErmsExecution|null} */
    private function findPayrollRun(string $sourceRef, string $requestNumber, string $ermsReference): array
    {
        $execution = null;
        if ($sourceRef !== '') {
            $execution = PayrollErmsExecution::query()->where('source_ref', $sourceRef)->first();
        }
        if ($execution === null && $ermsReference !== '') {
            $execution = PayrollErmsExecution::query()->where('erms_reference', $ermsReference)->first();
        }

        $run = $execution?->payrollRun;
        if ($run === null && $ermsReference !== '') {
            $run = PayrollRun::query()->where('erms_reference', $ermsReference)->first();
        }
        if ($run === null && $requestNumber !== '') {
            $run = PayrollRun::query()->where('erms_reference', $requestNumber)->first();
        }
        if ($run === null && $sourceRef !== '') {
            $run = PayrollRun::query()->where('payroll_number', $sourceRef)->first()
                ?? (ctype_digit($sourceRef) ? PayrollRun::query()->find((int) $sourceRef) : null);
        }

        return [$run, $execution];
    }

    /** @param array<string, mixed> $snapshot */
    private function applyToOvertimeBatch(OvertimeBatch $batch, array $snapshot, ?string $status): array
    {
        $response = is_array($batch->payment_reference_response) ? $batch->payment_reference_response : [];
        $response['erms_payable_callback'] = $snapshot;
        $batch->payment_reference_response = $response;

        if ($status !== null) {
            $batch->payment_status = $status;
        }
        if ($snapshot['request_number'] !== '') {
            $batch->payment_reference = (string) $snapshot['request_number'];
        }

        $batch->save();

        Log::info('ERMS payable webhook persisted (overtime batch)', [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'payment_status' => (string) $batch->payment_status,
        ]);

        return [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'payment_status' => (string) $batch->payment_status,
            'payment_reference' => (string) $batch->payment_reference,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function applyToPayrollRun(
        PayrollRun $run,
        array $snapshot,
        ?string $status,
        ?PayrollErmsExecution $execution = null,
    ): array {
        if ($status !== null) {
            $run->status = $status;
        }

        $ermsReference = trim((string) ($snapshot['erms_reference'] ?? ''));
        if ($ermsReference !== '') {
            $run->erms_reference = $ermsReference;
        } elseif ($snapshot['request_number'] !== '' && empty($run->erms_reference)) {
            $run->erms_reference = (string) $snapshot['request_number'];
        }

        if ($execution !== null) {
            $payload = is_array($execution->response_payload) ? $execution->response_payload : [];
            $payload['erms_payable_callback'] = $snapshot;
            $execution->response_payload = $payload;

            if ($snapshot['request_number'] !== '' && empty($execution->erms_reference)) {
                $execution->erms_reference = (string) $snapshot['request_number'];
            }

            $execution->save();
        }

        $run->save();

        Log::info('ERMS payable webhook persisted (payroll run)', [
            'run_id' => $run->id,
            'payroll_number' => $run->payroll_number,
            'status' => (string) $run->status,
            'erms_execution_id' => $execution?->id,
        ]);

        return [
            'run_id' => $run->id,
            'payroll_number' => $run->payroll_number,
            'status' => (string) $run->status,
            'erms_execution_id' => $execution?->id,
        ];
    }

    /** @return array{ok:bool, target:string|null, applied:array<string,mixed>|null, message:string, snapshot:array<string,mixed>} */
    private function result(bool $ok, ?string $target, ?array $applied, string $message, array $snapshot): array
    {
        return compact('ok', 'target', 'applied', 'message', 'snapshot');
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
