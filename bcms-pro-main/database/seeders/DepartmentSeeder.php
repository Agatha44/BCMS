<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('departments')
            ->upsert(
                [
                    [
                        'department_id' => 2,
                        'department_name' => 'Information Technology (IT)',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'department_id' => 3,
                        'department_name' => 'Weighbridge',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'department_id' => 4,
                        'department_name' => 'Registration',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'department_id' => 5,
                        'department_name' => 'Call Centre',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'department_id' => 6,
                        'department_name' => 'Toll Collection',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'department_id' => 7,
                        'department_name' => 'Administration',
                        'is_active' => true,
                        'created_by' => '1',
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['department_id'],
                [
                    'department_name',
                    'is_active',
                    'created_by',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Departments seeded successfully into bcmis2.departments.');
    }
}
