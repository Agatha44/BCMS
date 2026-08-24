<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        if (config('bridge_status.interface.schedule_refresh')) {
            $schedule->command('bridge-status:refresh-interface-snapshot')
                ->everyMinute()
                ->withoutOverlapping(10);
        }
        // $schedule->command('inspire')->hourly();
        $schedule->command('bridge-roles:sync-validity')->hourly();

        // Expired bundles: deactivate subscriptions (no HTTP/login). ERMS revenue posting is
        // handled separately by erms:retry-bundle-misc.
        $schedule->command('bundle:check-expiration')
            ->everyTenMinutes()
            ->withoutOverlapping(10);

        // Retry failed / non-posted sale receipts to ERMS (not payment requests).
        if (filter_var(config('erms.receipt_retry.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $everyMinutes = max(1, (int) config('erms.receipt_retry.schedule_every_minutes'));
            $overlapMinutes = max(1, (int) config('erms.receipt_retry.overlap_minutes'));

            $schedule->command('erms:retry-receipts')
                ->cron(sprintf('*/%d * * * *', $everyMinutes))
                ->withoutOverlapping($overlapMinutes);
        }

        // Retry failed / non-posted cashless toll miscellaneous entries to ERMS.
        if (filter_var(config('erms.miscellaneous_retry.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $everyMinutes = max(1, (int) config('erms.miscellaneous_retry.schedule_every_minutes'));
            $overlapMinutes = max(1, (int) config('erms.miscellaneous_retry.overlap_minutes'));

            $schedule->command('erms:retry-cashless-misc')
                ->cron(sprintf('*/%d * * * *', $everyMinutes))
                ->withoutOverlapping($overlapMinutes);
        }

        // Post/retry TBS bundle revenue recognition (miscellaneous entries) to ERMS.
        if (filter_var(config('erms.bundle_retry.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $everyMinutes = max(1, (int) config('erms.bundle_retry.schedule_every_minutes'));
            $overlapMinutes = max(1, (int) config('erms.bundle_retry.overlap_minutes'));

            $schedule->command('erms:retry-bundle-misc')
                ->cron(sprintf('*/%d * * * *', $everyMinutes))
                ->withoutOverlapping($overlapMinutes);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
