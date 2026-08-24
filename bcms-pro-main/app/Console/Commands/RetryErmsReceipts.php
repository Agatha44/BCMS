<?php

namespace App\Console\Commands;

use App\Services\Erms\ErmsReceiptRetryService;
use Illuminate\Console\Command;

class RetryErmsReceipts extends Command
{
    protected $signature = 'erms:retry-receipts
                            {--dry-run : List pending receipts without submitting to ERMS}
                            {--limit= : Maximum receipts per source type (default: config erms.receipt_retry.limit_per_source)}
                            {--type= : Process only one source type (overload_fine, incident_fine, bridge_bill, event_payment, top_up)}
                            {--force : Run even when erms.receipt_retry.enabled is false}';

    protected $description = 'Retry failed and non-posted sale receipts to ERMS';

    public function handle(ErmsReceiptRetryService $retryService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $retryService->isEnabled() && ! $force && ! $dryRun) {
            $this->warn('ERMS receipt retry is disabled (erms.receipt_retry.enabled). Use --force to run anyway.');

            return self::SUCCESS;
        }

        $limitOption = $this->option('limit');
        $limit = ($limitOption !== null && $limitOption !== '')
            ? max(1, (int) $limitOption)
            : null;

        $type = $this->option('type');
        if (is_string($type) && $type !== '') {
            if (! in_array($type, ErmsReceiptRetryService::supportedTypes(), true)) {
                $this->error('Invalid --type. Supported: '.implode(', ', ErmsReceiptRetryService::supportedTypes()));

                return self::FAILURE;
            }
        } else {
            $type = null;
        }

        if ($dryRun) {
            $this->info('Dry run: no receipts will be submitted to ERMS.');
        }

        $trxFrom = $retryService->trxDtTmFrom();
        if ($trxFrom !== null) {
            $this->line('Filtering receipts with trx_dt_tm >= '.$trxFrom);
        }

        $result = $retryService->retryPendingReceipts($limit, $dryRun, $type);

        if ($result['sources'] !== []) {
            $this->newLine();
            $this->line('Per source:');

            foreach ($result['sources'] as $key => $source) {
                if ($dryRun) {
                    $this->line(sprintf(
                        '  %s: %d would submit (%d pending)',
                        $key,
                        $source['dry_run'],
                        $source['pending_total']
                    ));
                } else {
                    $this->line(sprintf(
                        '  %s: posted %d, failed %d, skipped %d (%d pending)',
                        $key,
                        $source['posted'],
                        $source['failed'],
                        $source['skipped'],
                        $source['pending_total']
                    ));
                }
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'ERMS receipt retry complete. Posted: %d, failed: %d, skipped: %d%s.',
            $result['posted'],
            $result['failed'],
            $result['skipped'],
            $dryRun ? ', dry-run: '.$result['dry_run'] : ''
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
