<?php

namespace App\Services\Payroll;

use App\Models\Bms\Payroll\PayrollRun;
use App\Models\Bms\Payroll\PayrollRunHistory;
use DomainException;
use Illuminate\Support\Facades\DB;

class PayrollWorkflowService
{
    public function __construct(
        private PayrollItemWriter $writer,
        private PayrollPreviewProcessor $previewProcessor,
        private PayrollProcessor $processor
    ) {}

    /**
     * STEP 1: Prepare payroll (build payroll items)
     */
    public function prepare(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);
            
            $this->assertState($locked, ['draft']);

            $this->writer->writeForRun($locked);

            // Build preview transactions immediately after preparing items.
            // Safe to rerun (upsert) as long as run isn't posted.
            $this->generatePreview($locked, $userId);

            $locked->update([
                'status' => 'prepared',
                'prepared_by' => $userId,
                'prepared_at' => now(),
            ]);

            $this->logHistory($locked, 'Prepared', 'prepared', $userId, null, $comment);

            return $locked->fresh();
        });
    }

    /**
     * Generate/refresh preview transactions for review (no posting).
     */
    public function generatePreview(PayrollRun $run, string $userId): int
    {
        if (($run->status ?? 'draft') === 'posted') {
            throw new DomainException('Preview cannot be generated after payroll is posted.');
        }

        return $this->previewProcessor->generate($run, $userId);
    }

    public function initiate(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);
            $this->assertState($locked, ['prepared']);

            $locked->update([
                'status' => 'initiated',
                'initiated_by' => $userId,
                'initiated_at' => now(),
            ]);

            $this->logHistory($locked, 'Initiated', 'initiated', $userId, null, $comment);

            return $locked->fresh();
        });
    }

    public function verify(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);
            // Flow: prepared -> initiated -> examined -> verified -> approved -> posted
            $this->assertState($locked, ['examined']);

            $locked->update([
                'status' => 'verified',
                'verified_by' => $userId,
                'verified_at' => now(),
            ]);

            $this->logHistory($locked, 'Verified', 'verified', $userId, null, $comment);

            return $locked->fresh();
        });
    }

    public function examine(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);
            // Flow: prepared -> initiated -> examined -> verified -> approved -> posted
            $this->assertState($locked, ['initiated']);

            $locked->update([
                'status' => 'examined',
                'examined_by' => $userId,
                'examined_at' => now(),
            ]);

            $this->logHistory($locked, 'Examined', 'examined', $userId, null, $comment);

            return $locked->fresh();
        });
    }

    public function approve(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);
            // Flow: prepared -> examined -> verified -> approved -> posted
            $this->assertState($locked, ['verified']);

            $locked->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $this->logHistory($locked, 'Approved', 'approved', $userId, null, $comment);

            return $locked->fresh();
        });
    }

    /**
     * Return payroll for correction back to "prepared".
     * Allowed from: examined / verified / approved.
     */
    public function returnPayroll(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId, $comment) {
            $locked = $this->lockRun($run);

            $this->assertState($locked, ['examined', 'verified', 'approved']);

            $from = (string) ($locked->status ?? '');

            $locked->update([
                'status' => 'prepared',

                // Reset intermediate approvals so the run can cleanly move forward again.
                'initiated_by' => null,
                'initiated_at' => null,
                'examined_by' => null,
                'examined_at' => null,
                'verified_by' => null,
                'verified_at' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);

            $this->logHistory(
                $locked,
                'Returned',
                'prepared',
                $userId,
                null,
                $comment,
                $from
            );

            return $locked->fresh();
        });
    }

    /**
     * Backward-compatible alias (older clients may still call "reject").
     */
    public function reject(PayrollRun $run, string $userId, ?string $comment = null): PayrollRun
    {
        return $this->returnPayroll($run, $userId, $comment);
    }

    /**
     * STEP FINAL: Generate payroll transactions
     */
    public function process(PayrollRun $run, string $userId): int
    {
        return DB::connection('bcmis2')->transaction(function () use ($run, $userId) {
            $locked = $this->lockRun($run);
            
            $this->assertState($locked, ['approved']);

            // PayrollProcessor will set status to "posted" after successful persist.
            $count = $this->processor->process($locked, $userId);

            $fresh = PayrollRun::query()->find($locked->getKey());
            if ($fresh) {
                $this->logHistory($fresh, 'Processed', (string) ($fresh->status ?? 'posted'), $userId);
            } else {
                $this->logHistory($locked, 'Processed', (string) ($locked->status ?? 'posted'), $userId);
            }

            return $count;
        });
    }

    private function lockRun(PayrollRun $run): PayrollRun
    {
        $locked = PayrollRun::query()
            ->whereKey($run->getKey())
            ->lockForUpdate()
            ->first();

        if (! $locked) {
            throw new DomainException('Payroll run not found.');
        }

        return $locked;
    }

    /**
     * @param array<int, string> $allowed
     */
    private function assertState(PayrollRun $run, array $allowed): void
    {
        if (! in_array($run->status, $allowed, true)) {
            throw new DomainException("Invalid state transition. Current: {$run->status}");
        }
    }

    private function logHistory(
        PayrollRun $run,
        string $action,
        string $status,
        string $userId,
        ?string $performedByRole = null,
        ?string $comment = null,
        ?string $workflowStatus = null
    ): void {
        PayrollRunHistory::query()->create([
            'payroll_run_id' => (int) $run->getKey(),
            'action' => $action,
            'status' => $status,
            'workflow_status' => $workflowStatus,
            'performed_by' => $userId,
            'performed_by_role' => $performedByRole,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }
}

