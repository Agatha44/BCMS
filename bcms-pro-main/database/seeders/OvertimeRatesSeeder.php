<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OvertimeRatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('overtime_rates')
            ->upsert(
                [
                    [
                        'id' => 1,
                        'educational_levels_id' => 1,
                        'rate' => 30000,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 2,
                        'educational_levels_id' => 2,
                        'rate' => 30000,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 3,
                        'educational_levels_id' => 3,
                        'rate' => 40000,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 4,
                        'educational_levels_id' => 4,
                        'rate' => 40000,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                    [
                        'id' => 5,
                        'educational_levels_id' => 5,
                        'rate' => 60000,
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => 1,
                        'modified_at' => '2026-07-01 00:40:37',
                    ],
                ],
                ['id'],
                [
                    'educational_levels_id',
                    'rate',
                    'is_active',
                    'created_by',
                    'created_at',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Overtime rates seeded successfully into bcmis2.overtime_rates.');
    }
}
