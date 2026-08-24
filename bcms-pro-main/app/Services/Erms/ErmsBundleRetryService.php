<?php

namespace App\Services\Erms;

use App\Models\BundleSubscription;
use App\Services\Erms\Mappers\BundleSubscriptionMapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Retries ERMS revenue recognition (miscellaneous entries) for expired/deactivated TBS bundle
 * subscriptions. Deactivation is owned by the bundle:check-expiration command; this service only
 * posts revenue for subscriptions that are already inactive (status = 2) and not yet posted to ERMS.
 */
class ErmsBundleRetryService
{
    public function __construct(
        private ErmsMiscellaneousSubmissionService $submissionService,
        private BundleSubscriptionMapper $bundleSubscriptionMapper,
    ) {
    }

    public function isEnabled(): bool
    {
        return filter_var(config('erms.bundle_retry.enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public function resolveLimit(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return max(1, (int) config('erms.bundle_retry.limit'));
    }

    public function expireDateFrom(): ?string
    {
        $from = trim((string) config('erms.bundle_retry.expire_date_from'));

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
    public function retryPendingBundles(?int $limit = null, bool $dryRun = false): array
    {
        $counts = ['posted' => 0, 'failed' => 0, 'skipped' => 0, 'dry_run' => 0, 'pending_total' => 0];

        if (! Schema::hasTable('bundle_subscriptions')) {
            Log::warning('ERMS bundle retry: bundle_subscriptions table missing');

            return $counts;
        }

        if (! Schema::hasColumn('bundle_subscriptions', 'erms_status')) {
            Log::warning('ERMS bundle retry: erms_status column missing on bundle_subscriptions');

            return $counts;
        }

        $counts['pending_total'] = $this->countPendingRows();
        $rows = $this->fetchPendingRows($this->resolveLimit($limit));

        foreach ($rows as $row) {
            if ($dryRun) {
                $counts['dry_run']++;
                Log::info('ERMS bundle retry dry-run: would submit', [
                    'bundle_subscription_id' => $row->id,
                    'bill_id' => $row->bill_id,
                    'receipt_number' => $row->receipt_number ?? null,
                    'expire_date' => $row->expire_date ?? null,
                ]);

                continue;
            }

            $outcome = $this->submitBundleMisc($row);

            if ($outcome === 'posted') {
                $counts['posted']++;
                Log::info('ERMS bundle retry posted', [
                    'bundle_subscription_id' => $row->id,
                    'bill_id' => $row->bill_id,
                    'receipt_number' => $row->receipt_number ?? null,
                ]);
            } elseif ($outcome === 'failed') {
                $counts['failed']++;
                Log::warning('ERMS bundle retry failed', [
                    'bundle_subscription_id' => $row->id,
                    'bill_id' => $row->bill_id,
                    'receipt_number' => $row->receipt_number ?? null,
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
        $query = DB::table('bundle_subscriptions as bs')
            ->join('bridge_bills as bb', 'bb.id', '=', 'bs.bill_id')
            ->where('bs.status', (int) BundleSubscription::STATUS_INACTIVE)
            ->where('bb.source', 'TBS')
            ->where('bb.erp_status', 1)
            ->where(function ($q): void {
                $q->whereNull('bs.erms_status')
                    ->orWhere('bs.erms_status', '!=', 1);
            })
            ->whereNotNull('bb.psp_receipt_num')
            ->whereNotNull('bb.paid_amt')
            ->where('bb.paid_amt', '>', 0);

        $expireFrom = $this->expireDateFrom();
        if ($expireFrom !== null) {
            $query->where('bs.expire_date', '>=', $expireFrom);
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
        return $this->pendingRowsQuery()
            ->orderBy('bs.expire_date')
            ->limit(max(1, $limit))
            ->get([
                'bs.id',
                'bs.account_id',
                'bs.bill_id',
                'bs.expire_date',
                'bs.status',
                'bs.erms_status',
                'bb.paid_amt',
                'bb.receipt_number',
                'bb.dist_param',
                'bb.payer_name',
            ]);
    }

    /**
     * @return 'posted'|'failed'|'skipped'
     */
    private function submitBundleMisc(object $row): string
    {
        try {
            $account = null;
            if ($row->account_id !== null && $row->account_id !== '') {
                $account = DB::table('account')->where('account_no', $row->account_id)->first();
            }

            $payload = $this->bundleSubscriptionMapper->map($row, $account);
            $submission = $this->submissionService->submit($payload);

            DB::table('bundle_subscriptions')
                ->where('id', $row->id)
                ->update([
                    'erms_status' => $submission['ok'] ? 1 : 2,
                    'erms_submitted_at' => now(),
                ]);

            return $submission['ok'] ? 'posted' : 'failed';
        } catch (\Throwable $e) {
            DB::table('bundle_subscriptions')
                ->where('id', $row->id)
                ->update([
                    'erms_status' => 2,
                    'erms_submitted_at' => now(),
                ]);

            Log::error('ERMS bundle retry exception', [
                'bundle_subscription_id' => $row->id,
                'bill_id' => $row->bill_id,
                'error' => $e->getMessage(),
            ]);

            return 'failed';
        }
    }
}
