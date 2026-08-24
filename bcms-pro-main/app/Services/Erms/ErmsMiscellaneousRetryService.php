<?php

namespace App\Services\Erms;

use App\Models\Account;
use App\Models\TollTransaction;
use App\Services\Erms\Mappers\CashlessTollMiscellaneousMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ErmsMiscellaneousRetryService
{
    public function __construct(
        private ErmsMiscellaneousSubmissionService $submissionService,
        private CashlessTollMiscellaneousMapper $cashlessTollMiscellaneousMapper,
    ) {
    }

    public function isEnabled(): bool
    {
        return filter_var(config('erms.miscellaneous_retry.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function resolveLimit(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return max(1, (int) config('erms.miscellaneous_retry.limit'));
    }

    public function createdAtFrom(): ?string
    {
        $from = trim((string) config('erms.miscellaneous_retry.created_at_from'));

        return $from !== '' ? $from : null;
    }

    /**
     * @return array{
     *   posted: int,
     *   failed: int,
     *   skipped: int,
     *   dry_run: int,
     *   pending_total: int
     * }
     */
    public function retryPendingCashlessMisc(?int $limit = null, bool $dryRun = false): array
    {
        $counts = ['posted' => 0, 'failed' => 0, 'skipped' => 0, 'dry_run' => 0, 'pending_total' => 0];

        if (! Schema::hasTable('toll_transaction')) {
            Log::warning('ERMS cashless misc retry: toll_transaction table missing');

            return $counts;
        }

        if (! Schema::hasColumn('toll_transaction', 'erms_status')) {
            Log::warning('ERMS cashless misc retry: erms_status column missing on toll_transaction');

            return $counts;
        }

        $counts['pending_total'] = $this->countPendingRows();
        $rows = $this->fetchPendingRows($this->resolveLimit($limit));

        foreach ($rows as $row) {
            if (! $this->isEligibleForSubmission($row)) {
                $counts['skipped']++;

                continue;
            }

            if ($dryRun) {
                $counts['dry_run']++;
                Log::info('ERMS cashless misc retry dry-run: would submit', [
                    'toll_transaction_id' => $row->id,
                    'receipt_num' => $row->receipt_num ?? null,
                    'created_at' => $row->created_at ?? null,
                ]);

                continue;
            }

            $outcome = $this->submitCashlessMisc((int) $row->id);

            if ($outcome === 'posted') {
                $counts['posted']++;
                Log::info('ERMS cashless misc retry posted', [
                    'toll_transaction_id' => $row->id,
                    'receipt_num' => $row->receipt_num ?? null,
                ]);
            } elseif ($outcome === 'failed') {
                $counts['failed']++;
                Log::warning('ERMS cashless misc retry failed', [
                    'toll_transaction_id' => $row->id,
                    'receipt_num' => $row->receipt_num ?? null,
                ]);
            } else {
                $counts['skipped']++;
            }
        }

        return $counts;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function pendingRowsQuery()
    {
        $query = DB::table('toll_transaction')
            ->where('trans_type', 'CASHLESS')
            ->whereNotNull('charged_amount')
            ->where('charged_amount', '>', 0)
            ->where(function ($q): void {
                $q->whereNull('erms_status')
                    ->orWhere('erms_status', '!=', 1);
            });

        $createdFrom = $this->createdAtFrom();
        if ($createdFrom !== null && Schema::hasColumn('toll_transaction', 'created_at')) {
            $query->whereNotNull('created_at')->where('created_at', '>=', $createdFrom);
        }

        return $query;
    }

    private function countPendingRows(): int
    {
        return $this->pendingRowsQuery()->count();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function fetchPendingRows(int $limit)
    {
        $orderColumn = (string) config('erms.miscellaneous_retry.order_column', 'created_at');
        $query = $this->pendingRowsQuery()->limit(max(1, $limit));

        if (Schema::hasColumn('toll_transaction', $orderColumn)) {
            $query->orderBy($orderColumn);
        } else {
            $query->orderBy('id');
        }

        return $query->get(['id', 'account_no', 'receipt_num', 'charged_amount', 'erms_status', 'created_at']);
    }

    private function isEligibleForSubmission(object $row): bool
    {
        if ((float) ($row->charged_amount ?? 0) <= 0) {
            return false;
        }

        return (int) ($row->erms_status ?? 0) !== 1;
    }

    /**
     * @return 'posted'|'failed'|'skipped'
     */
    private function submitCashlessMisc(int $tollId): string
    {
        try {
            $toll = TollTransaction::query()->find($tollId);
            if ($toll === null) {
                return 'skipped';
            }

            $account = $this->resolveAccount($toll);
            if ($account === null) {
                Log::warning('ERMS cashless misc retry skipped: account not found', [
                    'toll_transaction_id' => $tollId,
                    'account_no' => $toll->account_no,
                ]);

                return 'skipped';
            }

            $payload = $this->cashlessTollMiscellaneousMapper->map($toll, $account);
            $submission = $this->submissionService->submit($payload);

            TollTransaction::query()
                ->where('id', $tollId)
                ->update(['erms_status' => $submission['ok'] ? 1 : 2]);

            return $submission['ok'] ? 'posted' : 'failed';
        } catch (\Throwable $e) {
            TollTransaction::query()
                ->where('id', $tollId)
                ->update(['erms_status' => 2]);

            Log::error('ERMS cashless misc retry exception', [
                'toll_transaction_id' => $tollId,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }

    private function resolveAccount(TollTransaction $toll): ?Account
    {
        $accountNo = trim((string) ($toll->account_no ?? ''));
        if ($accountNo === '') {
            return null;
        }

        return Account::query()->where('account_no', $accountNo)->first();
    }
}
