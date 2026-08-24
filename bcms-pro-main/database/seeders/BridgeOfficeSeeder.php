<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BridgeOfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $offices = [
            [
                'office_name' => 'Nyerere Bridge',
                'office_code' => 'NYERERE_BRIDGE',
                'auto_generate_pf' => true,
                'description' => 'Nyerere Bridge office - auto-generates PF numbers',
                'is_active' => true,
                'created_at' => now(),
            ],
            [
                'office_name' => 'Main Scheme',
                'office_code' => 'MAIN_SCHEME',
                'auto_generate_pf' => false,
                'description' => 'Requires manual PF number entry',
                'is_active' => true,
                'created_at' => now(),
            ],
        ];

        foreach ($offices as $office) {
            DB::connection('bcmis2')->table('bridge_office')->updateOrInsert(
                ['office_code' => $office['office_code']],
                $office
            );
        }

        $this->command->info('Bridge offices seeded successfully!');
    }
}

