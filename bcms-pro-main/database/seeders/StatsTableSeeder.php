<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatsTableSeeder extends Seeder
{
    public function run()
    {
        // Insert some dummy stats data for category 1
        DB::table('report_statistics')->insert([
            [
                'category_id' => 1,
                'name' => 'Total Toll Collection',
                'value' => 12334.00,
            ],
            [
                'category_id' => 1,
                'name' => 'Total Cash Collection',
                'value' => 12334.00,
            ],
            [
                'category_id' => 1,
                'name' => 'Total Prepayment Collection',
                'value' => 12334.00,
            ],
        ]);

        // Insert some dummy stats data for category 2
        DB::table('report_statistics')->insert([
            [
                'category_id' => 2,
                'name' => 'Total Users',
                'value' => 12334.00,
            ],
            [
                'category_id' => 2,
                'name' => 'Active Users',
                'value' => 12334.00,
            ],
            [
                'category_id' => 2,
                'name' => 'In-Active Users',
                'value' => 12334.00,
            ],
        ]);
    }
}
