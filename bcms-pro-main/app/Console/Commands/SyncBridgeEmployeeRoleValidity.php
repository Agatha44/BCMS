<?php

namespace App\Console\Commands;

use App\Models\Bms\BridgeEmployeeRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBridgeEmployeeRoleValidity extends Command
{
    protected $signature = 'bridge-roles:sync-validity {--dry-run : Show counts without applying changes}';

    protected $description = 'Activate roles when from_date is reached and revoke roles when to_date is passed';

    public function handle(): int
    {
        $today = now()->toDateString();
        $now = now();
        $dryRun = (bool) $this->option('dry-run');

        try {
            $expiredQuery = BridgeEmployeeRole::query()
                ->where('is_active', true)
                ->whereNotNull('to_date')
                ->where('to_date', '<', $today);

            $dueActivationQuery = BridgeEmployeeRole::query()
                ->where('is_active', false)
                ->whereNull('revoked_at')
                ->where(function ($q) use ($today) {
                    $q->whereNull('from_date')->orWhere('from_date', '<=', $today);
                })
                ->where(function ($q) use ($today) {
                    $q->whereNull('to_date')->orWhere('to_date', '>=', $today);
                });

            $expiredCount = (clone $expiredQuery)->count();
            $activateCount = (clone $dueActivationQuery)->count();

            if ($dryRun) {
                $this->info("Expired to revoke: {$expiredCount}");
                $this->info("Due to activate: {$activateCount}");
                return Command::SUCCESS;
            }

            DB::connection('bcmis2')->beginTransaction();

            $revoked = 0;
            if ($expiredCount > 0) {
                $revoked = $expiredQuery->update([
                    'is_active' => false,
                    'revoked_at' => $now,
                    'revoked_by' => 'system',
                    'modified_at' => $now,
                    'modified_by' => 'system',
                ]);
            }

            $activated = 0;
            if ($activateCount > 0) {
                $activated = $dueActivationQuery->update([
                    'is_active' => true,
                    'modified_at' => $now,
                    'modified_by' => 'system',
                ]);
            }

            DB::connection('bcmis2')->commit();

            Log::info('Synced bridge employee role validity', [
                'revoked' => $revoked,
                'activated' => $activated,
                'today' => $today,
            ]);

            $this->info("Revoked expired roles: {$revoked}");
            $this->info("Activated roles: {$activated}");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::connection('bcmis2')->rollBack();
            Log::error('Failed to sync bridge employee role validity', [
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}

