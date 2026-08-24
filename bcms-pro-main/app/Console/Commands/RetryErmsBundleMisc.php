<?php

namespace App\Console\Commands;

use App\Services\Erms\ErmsBundleRetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryErmsBundleMisc extends Command
{
    protected $signature = 'erms:retry-bundle-misc
                            {--dry-run : List pending TBS bundle misc entries without submitting to ERMS}
                            {--limit= : Maximum bundle subscriptions to process (default: config erms.bundle_retry.limit)}
                            {--force : Run even when erms.bundle_retry.enabled is false}';

    protected $description = 'Retry failed and non-posted TBS bundle miscellaneous entries (revenue recognition) to ERMS';

    public function handle(ErmsBundleRetryService $retryService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $retryService->isEnabled() && ! $force && ! $dryRun) {
            $this->warn('ERMS bundle retry is disabled (erms.bundle_retry.enabled). Use --force to run anyway.');

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '')
            ? max(1, (int) $limitOption)
            : null;

        if ($dryRun) {
            $this->info('Dry run: no bundle misc entries will be submitted to ERMS.');
        }

        $expireFrom = $retryService->expireDateFrom();
        if ($expireFrom !== null) {
            $this->line('Filtering bundle subscriptions with expire_date >= '.$expireFrom);
        }

        $result = $retryService->retryPendingBundles($limit, $dryRun);
        Log::info('RetryErmsBundleMisc: result', ['result' => $result]);

        $this->newLine();
        if ($dryRun) {
            $this->line(sprintf(
                'Bundle misc: %d would submit (%d pending)',
                $result['dry_run'],
                $result['pending_total']
            ));
        } else {
            $this->line(sprintf(
                'Bundle misc: posted %d, failed %d, skipped %d (%d pending)',
                $result['posted'],
                $result['failed'],
                $result['skipped'],
                $result['pending_total']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'ERMS bundle retry complete. Posted: %d, failed: %d, skipped: %d%s.',
            $result['posted'],
            $result['failed'],
            $result['skipped'],
            $dryRun ? ', dry-run: '.$result['dry_run'] : ''
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
