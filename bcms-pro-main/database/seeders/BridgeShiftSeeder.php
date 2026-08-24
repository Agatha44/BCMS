<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BridgeShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::connection('bcmis2')
            ->table('bridge_shifts')
            ->upsert(
                [
                    [
                        'id' => 1,
                        'shift_name' => 'Full Time Shift',
                        'start_time' => '08:00:00',
                        'end_time' => '17:00:00',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 2,
                        'shift_name' => 'IT and Toll Collector Morning Shift',
                        'start_time' => '07:00:00',
                        'end_time' => '13:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 3,
                        'shift_name' => 'IT and Toll Collector Evening Shift',
                        'start_time' => '14:00:00',
                        'end_time' => '21:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 4,
                        'shift_name' => 'IT and Toll Collector Night Shift',
                        'start_time' => '22:00:00',
                        'end_time' => '06:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 5,
                        'shift_name' => 'Registration Morning Shift',
                        'start_time' => '08:00:00',
                        'end_time' => '13:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 6,
                        'shift_name' => 'Registration Evening Shift',
                        'start_time' => '14:00:00',
                        'end_time' => '20:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 7,
                        'shift_name' => 'Weighbridge Morning Shift',
                        'start_time' => '07:00:00',
                        'end_time' => '13:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 8,
                        'shift_name' => 'Weighbridge Evening Shift',
                        'start_time' => '14:00:00',
                        'end_time' => '21:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'id' => 9,
                        'shift_name' => 'Weighbridge Night Shift',
                        'start_time' => '22:00:00',
                        'end_time' => '06:59:59',
                        'is_active' => true,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['id'],
                [
                    'shift_name',
                    'start_time',
                    'end_time',
                    'is_active',
                    'created_by',
                    'created_at',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Bridge shifts seeded successfully into bcmis2.bridge_shifts.');
    }
}
