<?php

namespace App\Console\Commands;

use App\Services\Erms\ErmsMiscellaneousRetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryErmsCashlessMisc extends Command
{
    protected $signature = 'erms:retry-cashless-misc
                            {--dry-run : List pending cashless toll misc entries without submitting to ERMS}
                            {--limit= : Maximum toll transactions to process (default: config erms.miscellaneous_retry.limit)}
                            {--force : Run even when erms.miscellaneous_retry.enabled is false}';

    protected $description = 'Retry failed and non-posted cashless toll miscellaneous entries to ERMS';

    public function handle(ErmsMiscellaneousRetryService $retryService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        Log::info('RetryErmsCashlessMisc: dry-run', ['dry-run' => $dryRun]);
        $force = (bool) $this->option('force');

        Log::info('RetryErmsCashlessMisc: force', ['force' => $force]);

        if (! $retryService->isEnabled() && ! $force && ! $dryRun) {
            $this->warn('ERMS cashless misc retry is disabled (erms.miscellaneous_retry.enabled). Use --force to run anyway.');

            Log::info('RetryErmsCashlessMisc: success');
            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        Log::info('RetryErmsCashlessMisc: limitOption', ['limitOption' => $limitOption]);
        $limit = ($limitOption !== null && $limitOption !== '')
            ? max(1, (int) $limitOption)
            : null;

        if ($dryRun) {
            $this->info('Dry run: no cashless misc entries will be submitted to ERMS.');
            Log::info('RetryErmsCashlessMisc: dry-run success');
        }

        $createdFrom = $retryService->createdAtFrom();
        Log::info('RetryErmsCashlessMisc: createdFrom', ['createdFrom' => $createdFrom]);
        if ($createdFrom !== null) {
            $this->line('Filtering toll transactions with created_at >= '.$createdFrom);
            Log::info('RetryErmsCashlessMisc: createdFrom success');
        }

        $result = $retryService->retryPendingCashlessMisc($limit, $dryRun);
        Log::info('RetryErmsCashlessMisc: result', ['result' => $result]);

        $this->newLine();
        Log::info('RetryErmsCashlessMisc: newLine success');
        if ($dryRun) {
            $this->line(sprintf(
                'Cashless misc: %d would submit (%d pending)',
                $result['dry_run'],
                $result['pending_total']
            ));
        } else {
            $this->line(sprintf(
                'Cashless misc: posted %d, failed %d, skipped %d (%d pending)',
                $result['posted'],
                $result['failed'],
                $result['skipped'],
                $result['pending_total']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'ERMS cashless misc retry complete. Posted: %d, failed: %d, skipped: %d%s.',
            $result['posted'],
            $result['failed'],
            $result['skipped'],
            $dryRun ? ', dry-run: '.$result['dry_run'] : ''
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
