<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxBracketsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        DB::connection('bcmis2')
            ->table('tax_brackets')
            ->upsert(
                [
                    [
                        'tax_bracket_id' => 1,
                        'taxable_income_range_start' => '0.00',
                        'taxable_income_range_end' => '270000.00',
                        'tax_rate_percentage' => '0.0000',
                        'base_tax_amount' => '0.00',
                        'is_active' => true,
                        'effective_start_date' => null,
                        'effective_end_date' => null,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'tax_bracket_id' => 2,
                        'taxable_income_range_start' => '270001.00',
                        'taxable_income_range_end' => '520000.00',
                        'tax_rate_percentage' => '8.0000',
                        'base_tax_amount' => '0.00',
                        'is_active' => true,
                        'effective_start_date' => null,
                        'effective_end_date' => null,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'tax_bracket_id' => 3,
                        'taxable_income_range_start' => '520001.00',
                        'taxable_income_range_end' => '760000.00',
                        'tax_rate_percentage' => '20.0000',
                        'base_tax_amount' => '20000.00',
                        'is_active' => true,
                        'effective_start_date' => null,
                        'effective_end_date' => null,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'tax_bracket_id' => 4,
                        'taxable_income_range_start' => '760001.00',
                        'taxable_income_range_end' => '1000000.00',
                        'tax_rate_percentage' => '25.0000',
                        'base_tax_amount' => '68000.00',
                        'is_active' => true,
                        'effective_start_date' => null,
                        'effective_end_date' => null,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                    [
                        'tax_bracket_id' => 5,
                        'taxable_income_range_start' => '1000001.00',
                        'taxable_income_range_end' => '1000000000.00',
                        'tax_rate_percentage' => '30.0000',
                        'base_tax_amount' => '128000.00',
                        'is_active' => true,
                        'effective_start_date' => null,
                        'effective_end_date' => null,
                        'created_by' => 1,
                        'created_at' => '2026-07-01 00:40:37',
                        'modified_by' => null,
                        'modified_at' => null,
                    ],
                ],
                ['tax_bracket_id'],
                [
                    'taxable_income_range_start',
                    'taxable_income_range_end',
                    'tax_rate_percentage',
                    'base_tax_amount',
                    'is_active',
                    'effective_start_date',
                    'effective_end_date',
                    'modified_by',
                    'modified_at',
                ]
            );

        $this->command?->info('Tax brackets seeded successfully into bcmis2.tax_brackets.');
    }
}
