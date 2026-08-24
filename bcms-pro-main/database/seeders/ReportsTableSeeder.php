<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;
class ReportsTableSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Generate 10 reports with random data
        for ($i = 0; $i < 10; $i++) {
            DB::table('reports')->insert([
                'name' => $faker->sentence,
                'description' => $faker->paragraph,
                'notes' => $faker->sentence,
                'category_id' => $faker->numberBetween(1, 5),
                'data_source' => $faker->url,
                'data_fields' => json_encode(['field1' => $faker->word, 'field2' => $faker->word]),
                'parameters' => json_encode(['param1' => $faker->word, 'param2' => $faker->word]),
                'status' => $faker->randomElement(['0', '1']),
                'is_public' => $faker->boolean(),
                'created_by' => $faker->numberBetween(1, 10),
                'updated_by' => null,
                'last_run_at' => $faker->dateTime(),
                'last_run_by' => $faker->numberBetween(1, 10),
                'created_at' => $faker->dateTime(),
            ]);
        }
    }
}
