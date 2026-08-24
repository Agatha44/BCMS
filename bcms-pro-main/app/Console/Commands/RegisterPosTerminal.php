<?php

namespace App\Console\Commands;

use App\Models\Lane;
use App\Models\PosTerminal;
use Illuminate\Console\Command;

class RegisterPosTerminal extends Command
{
    protected $signature = 'pos:register-terminal
                            {mac : Terminal MAC address}
                            {--lane-id= : Lane ID}
                            {--lane-number= : Lane number (e.g. A2)}
                            {--name= : Terminal display name}
                            {--ip=0.0.0.0 : Terminal IP address}';

    protected $description = 'Register a roadside POS terminal in pos_terminals';

    public function handle(): int
    {
        $macAddress = strtoupper(trim((string) $this->argument('mac')));
        $laneId = $this->option('lane-id');
        $laneNumber = $this->option('lane-number');

        $lane = null;
        if ($laneId) {
            $lane = Lane::find($laneId);
        } elseif ($laneNumber) {
            $lane = Lane::where('lane_no', $laneNumber)->first();
        }

        if (!$lane) {
            $this->error('Lane not found. Pass --lane-id or --lane-number.');

            return self::FAILURE;
        }

        $existing = PosTerminal::where('mac_address', $macAddress)->first();
        if ($existing) {
            $existing->lane_id = $lane->id;
            $existing->lane_number = $lane->lane_no;
            $existing->status = PosTerminal::STATUS_ACTIVE;
            $existing->ip_address = (string) $this->option('ip');
            if ($this->option('name')) {
                $existing->name = (string) $this->option('name');
            }
            $existing->save();

            $this->info("Updated existing terminal #{$existing->id} for lane {$lane->lane_no} ({$macAddress}).");

            return self::SUCCESS;
        }

        $conflict = PosTerminal::where('lane_id', $lane->id)
            ->where('status', PosTerminal::STATUS_ACTIVE)
            ->first();

        if ($conflict) {
            $this->error("Lane {$lane->lane_no} already has active terminal: {$conflict->name} ({$conflict->mac_address})");

            return self::FAILURE;
        }

        $terminal = PosTerminal::create([
            'name' => $this->option('name') ?? 'POS Lane ' . $lane->lane_no,
            'lane_id' => $lane->id,
            'lane_number' => $lane->lane_no,
            'mac_address' => $macAddress,
            'ip_address' => (string) $this->option('ip'),
            'terminal_type' => PosTerminal::TYPE_POS,
            'location' => 'Lane ' . $lane->lane_no,
            'status' => PosTerminal::STATUS_ACTIVE,
            'configuration' => [],
            'registered_by' => 1,
        ]);

        $terminal->generateApiKey();

        $this->info("Registered terminal #{$terminal->id} on lane {$lane->lane_no} with MAC {$macAddress}.");

        return self::SUCCESS;
    }
}
