<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('tax_brackets', function (Blueprint $table) {
            $table->id('tax_bracket_id')->comment('Unique tax bracket identifier');

            $table->decimal('taxable_income_range_start', 15, 2)->comment('Taxable income range start amount (inclusive)');
            $table->decimal('taxable_income_range_end', 15, 2)->comment('Taxable income range end amount (inclusive)');

            $table->decimal('tax_rate_percentage', 8, 4)->comment('Tax rate percentage applied for this bracket (e.g. 30.0000 = 30%)');
            $table->decimal('base_tax_amount', 15, 2)->default(0)->comment('Base tax amount for this bracket (fixed component)');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this tax bracket is active');

            $table->date('effective_start_date')->nullable()->comment('Date when this bracket becomes effective');
            $table->date('effective_end_date')->nullable()->comment('Date when this bracket stops being effective');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index(['taxable_income_range_start', 'taxable_income_range_end'], 'idx_tax_table_income_range');
            $table->index('is_active');
            $table->index('effective_start_date');
            $table->index('effective_end_date');
            $table->index('created_at');

            // Avoid duplicate brackets with the same range (helps keep "between" queries deterministic)
            $table->unique(
                ['taxable_income_range_start', 'taxable_income_range_end', 'effective_start_date'],
                'uniq_tax_table_range_effective_start'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('tax_brackets');
    }
};
