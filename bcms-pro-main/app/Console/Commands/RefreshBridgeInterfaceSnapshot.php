<?php

namespace App\Console\Commands;

use App\Services\BridgeInterfaceStatusService;
use Illuminate\Console\Command;

class RefreshBridgeInterfaceSnapshot extends Command
{
    protected $signature = 'bridge-status:refresh-interface-snapshot';

    protected $description = 'Pre-warm the cached bridge interface status snapshot';

    public function handle(BridgeInterfaceStatusService $service): int
    {
        $started = microtime(true);
        $snapshot = $service->refreshCache();
        $elapsedMs = (int) round((microtime(true) - $started) * 1000);

        $this->info("Bridge interface snapshot refreshed in {$elapsedMs}ms (status={$snapshot['status']}).");

        return self::SUCCESS;
    }
}
