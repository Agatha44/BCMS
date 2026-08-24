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
        Schema::connection('bcmis2')->create('benefit_type', function (Blueprint $table) {
            $table->id('benefit_type_id')->comment('Unique benefit type identifier');

            $table->string('benefit_name', 255)->comment('Benefit name');
            $table->string('benefit_code', 50)->unique()->comment('Short unique code e.g. HRA, TRANSPORT, FOOD, etc.');

            $table->enum('calculation_type', ['percentage', 'fixed'])->comment('Calculation type');

            $table->decimal('calculation_value', 12, 2)->comment('Calculation value');

            $table->boolean('is_taxable')
                ->default(true)
                ->comment('Whether this benefit is taxable');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this benefit is active');

            $table->string('contract_type', 100)->nullable()->comment('Contract type');
            $table->string('department_section', 200)->nullable()->comment('Department / section');
            $table->string('job_title_position', 255)->nullable()->comment('Job title / position');

            $table->date('start_date')->nullable()->comment('Benefit start date');

            $table->date('end_date')->nullable()->comment('Benefit end date');


            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes for better performance
            $table->index('benefit_name');
            $table->index('benefit_code');
            $table->index('is_taxable');
            $table->index('calculation_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('benefit_type');
    }
};

