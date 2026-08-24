<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds Administration Management module, role access, and menus.
 * Safe to run after BridgeModuleSeeder — uses upsert via child seeders.
 */
class AdministrationManagementModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BridgeModuleSeeder::class,
            BridgeModuleRoleSeeder::class,
            BridgeModuleMenuSeeder::class,
        ]);

        $this->command?->info('Administration Management module seeded (id: 10, module_id: administration-management).');
    }
}
