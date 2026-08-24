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
        Schema::connection('bcmis2')->create('deduction_type', function (Blueprint $table) {
            $table->id('deduction_type_id')->comment('Unique deduction type identifier');

            $table->string('deduction_name', 255)->comment('Full deduction name');
            $table->string('deduction_code', 50)->unique()->comment('Short unique code e.g. NSSF, NHIF, PAYE_ADJ, UNION, etc.');

            $table->enum('calculation_type', ['fixed', 'percentage']);

            $table->decimal('calculation_value', 15, 2)
                ->nullable()
                ->comment('Value applied when calculation_type is fixed or percentage');

            $table->boolean('is_before_tax')
                ->default(false)
                ->comment('Whether this deduction is applied before tax (pre-tax) when computing taxable income');

            $table->decimal('employee_contribution_percentage', 8, 2)
                ->nullable()
                ->comment('Employee percentage contribution (applied to salary base when is_fixed_amount is false)');

            $table->decimal('employer_contribution_percentage', 8, 2)
                ->nullable()
                ->comment('Employer percentage contribution (applied to salary base when is_fixed_amount is false)');

            $table->integer('priority')->default(0)->comment('Execution priority');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this deduction type is active');

            $table->string('contract_type', 100)->nullable()->comment('Contract type scope (if deduction applies only to a specific contract type)');

            $table->string('department_section', 200)->nullable()->comment('Department / section scope (if deduction applies only to a specific department/section)');

            $table->string('job_title_position', 255)->nullable()->comment('Job title / position scope (if deduction applies only to a specific job title/position)');

            $table->date('start_date')->nullable()->comment('Deduction start date');
            $table->date('end_date')->nullable()->comment('Deduction end date');

            $table->string('created_by', 50)->nullable()->comment('PF number who created');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->string('modified_by', 50)->nullable()->comment('PF number who last modified');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            $table->index('deduction_name');
            $table->index('calculation_type');
            $table->index('is_before_tax');
            $table->index('is_active');
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
        Schema::connection('bcmis2')->dropIfExists('deduction_type');
    }
};

