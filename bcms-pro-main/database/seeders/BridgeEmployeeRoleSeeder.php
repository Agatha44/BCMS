<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BridgeEmployeeRoleSeeder extends Seeder
{
    public function run(): void
    {
        $paths = [
            database_path('seeders/data/bridge_employee_role.json'),
            database_path('seeders/data/bridge_employee_role_additional.json'),
        ];

        $inserted = 0;
        $skipped = 0;

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $this->command?->warn("Skipping missing seed data file: {$path}");
                continue;
            }

            $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if ($rows === []) {
                $this->command?->warn('No bridge employee role rows to seed in ' . basename($path));

                continue;
            }

            foreach ($rows as $row) {
                if (empty($row['national_id']) || empty($row['role_id'])) {
                    $skipped++;
                    continue;
                }

                $exists = DB::connection('bcmis2')
                    ->table('bridge_employee_role')
                    ->where('national_id', $row['national_id'])
                    ->where('role_id', $row['role_id'])
                    ->where('is_active', true)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                DB::connection('bcmis2')->table('bridge_employee_role')->insert([
                    'national_id' => $row['national_id'],
                    'role_id' => $row['role_id'],
                    'from_date' => $row['from_date'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'description' => $row['description'] ?? 'Migrated from bcms',
                    'created_at' => $row['created_at'] ?? now(),
                    'modified_at' => $row['modified_at'] ?? now(),
                ]);

                $inserted++;
            }
        }

        $this->command?->info("bridge_employee_role seeded: {$inserted} inserted, {$skipped} skipped.");
    }
}
