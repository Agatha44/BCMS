<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TollCaptureDummySeeder extends Seeder
{
    /**
     * Seed a pending toll capture for POS testing (no ANPR required).
     */
    public function run(): void
    {
        Artisan::call('toll-capture:seed-test', [
            '--lane-number' => '1',
        ]);

        $this->command?->info(Artisan::output());
    }
}
