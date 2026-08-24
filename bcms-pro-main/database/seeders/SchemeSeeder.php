<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('scheme')
            ->upsert(
                [
                    [
                        'id' => 1,
                        'scheme_name' => 'PSSSF',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 2,
                        'scheme_name' => 'NSSF',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['id'],
                [
                    'scheme_name',
                    'is_active',
                    'created_by',
                    'created_at',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Schemes seeded successfully into bcmis2.scheme.');
    }
}
