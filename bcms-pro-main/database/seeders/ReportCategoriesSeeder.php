<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
class ReportCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $categories = [
            [
                'name' => 'Expenditure Reports',
                'description' => 'Reports related to expenditure',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Toll Collection Reports',
                'description' => 'Reports related to toll collection',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Administration Reports',
                'description' => 'Reports related to administration',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Bridge Charges Reports',
                'description' => 'Reports related to bridge charges',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ],
            [
                'name' => 'Overall Reports',
                'description' => 'Overall reports',
                'status' => 1,
                'created_by' => 1,
                'updated_by' => 1,
            ],
        ];

        DB::table('report_categories')->insert($categories);
    }
}
