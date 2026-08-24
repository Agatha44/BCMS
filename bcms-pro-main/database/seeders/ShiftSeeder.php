<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $shifts = [
            [
                'id' => 1,
                'name' => 'Morning',
                'description' => '06:00 to 12:59',
                'shift_start_time' => '06:00:00',
                'shift_end_time' => '12:59:59',
            ],
            [
                'id' => 2,
                'name' => 'Afternoon',
                'description' => '13:00 to 20:59',
                'shift_start_time' => '13:00:00',
                'shift_end_time' => '20:59:59',
            ],
            [
                'id' => 3,
                'name' => 'Evening',
                'description' => '21:00 to 05:59',
                'shift_start_time' => '21:00:00',
                'shift_end_time' => '05:59:59',
            ],
        ];

        foreach ($shifts as $shift) {
            // Check if shift exists
            $exists = DB::table('shift')->where('id', $shift['id'])->exists();
            
            if ($exists) {
                // Update existing shift with times
                DB::table('shift')
                    ->where('id', $shift['id'])
                    ->update([
                        'shift_start_time' => $shift['shift_start_time'],
                        'shift_end_time' => $shift['shift_end_time'],
                    ]);
            } else {
                // Insert new shift (if needed)
                DB::table('shift')->insert($shift);
            }
        }

        $this->command->info('Shift times seeded successfully!');
    }
}
