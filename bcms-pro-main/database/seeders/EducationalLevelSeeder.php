<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationalLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('educational_levels')
            ->upsert(
                [
                    [
                        'id' => 1,
                        'level_name' => 'Primary',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 2,
                        'level_name' => 'Secondary',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 3,
                        'level_name' => 'Certificate',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 4,
                        'level_name' => 'Diploma',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 5,
                        'level_name' => "Bachelor's Degree",
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 6,
                        'level_name' => "Master's Degree",
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 7,
                        'level_name' => 'Phd',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                ],
                ['id'],
                [
                    'level_name',
                    'is_active',
                    'created_by',
                    'created_at',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Educational levels seeded successfully into bcmis2.educational_levels.');
    }
}
