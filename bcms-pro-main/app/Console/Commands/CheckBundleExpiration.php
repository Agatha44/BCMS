<?php

namespace App\Console\Commands;

use App\Models\BundleSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CheckBundleExpiration extends Command
{
    protected $signature = 'bundle:check-expiration
                            {--dry-run : Report expired subscriptions without deactivating}';

    protected $description = 'Deactivate expired bundle subscriptions (ERMS revenue posting is handled by erms:retry-bundle-misc)';

    public function handle(): int
    {
        if (! Schema::hasTable('bundle_subscriptions')) {
            $this->error('bundle_subscriptions table not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $pending = $this->countExpiredActiveSubscriptions();
            $this->info("Dry run: {$pending} expired active subscription(s) would be deactivated.");

            return self::SUCCESS;
        }

        $deactivated = $this->deactivateExpiredSubscriptions();
        if ($deactivated > 0) {
            $this->info("Deactivated {$deactivated} expired subscription(s).");
        } else {
            $this->info('No expired active subscriptions to deactivate.');
        }

        return self::SUCCESS;
    }

    protected function countExpiredActiveSubscriptions(): int
    {
        return DB::table('bundle_subscriptions')
            ->where('status', (int) BundleSubscription::STATUS_ACTIVE)
            ->where('expire_date', '<', now())
            ->count();
    }

    protected function deactivateExpiredSubscriptions(): int
    {
        try {
            $updated = DB::table('bundle_subscriptions')
                ->where('status', (int) BundleSubscription::STATUS_ACTIVE)
                ->where('expire_date', '<', now())
                ->update([
                    'status' => (int) BundleSubscription::STATUS_INACTIVE,
                    'updated_at' => now(),
                    'updated_by' => 0,
                ]);

            if ($updated > 0) {
                Log::info('bundle:check-expiration deactivated subscriptions', ['updated_count' => $updated]);
            }

            return $updated;
        } catch (\Throwable $e) {
            Log::error('bundle:check-expiration deactivation failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
