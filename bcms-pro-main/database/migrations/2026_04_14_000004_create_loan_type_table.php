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
        Schema::connection('bcmis2')->create('loan_type', function (Blueprint $table) {
            $table->id('loan_type_id')->comment('Unique loan type identifier');

            $table->string('loan_name', 255)->comment('Full loan type name');
            $table->string('loan_code', 50)->unique()->comment('Short unique code e.g. SALARY_ADVANCE, CAR_LOAN, STAFF_LOAN, etc.');

            $table->boolean('has_interest')
                ->default(true)
                ->comment('Whether this loan type charges interest');

            $table->decimal('interest_percentage', 8, 2)
                ->nullable()
                ->comment('Interest percentage applied to the loan amount when has_interest is true');

            $table->enum('interest_calculation_method', ['flat', 'reducing_balance'])
                ->nullable()
                ->comment('How interest is calculated when has_interest is true');

            $table->decimal('minimum_loan_amount', 15, 2)
                ->nullable()
                ->comment('Minimum loan amount allowed for this loan type');

            $table->decimal('maximum_loan_amount', 15, 2)
                ->nullable()
                ->comment('Maximum loan amount allowed for this loan type');

            $table->unsignedInteger('minimum_repayment_months')
                ->nullable()
                ->comment('Minimum repayment period in months');

            $table->unsignedInteger('maximum_repayment_months')
                ->nullable()
                ->comment('Maximum repayment period in months');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this loan type is active');

            $table->integer('priority')->default(0)->comment('Execution priority');

            $table->string('contract_type', 100)->nullable()->comment('Contract type scope (if loan applies only to a specific contract type)');
            $table->string('department_section', 200)->nullable()->comment('Department / section scope (if loan applies only to a specific department/section)');
            $table->string('job_title_position', 255)->nullable()->comment('Job title / position scope (if loan applies only to a specific job title/position)');

            $table->date('start_date')->nullable()->comment('Loan type start date');
            $table->date('end_date')->nullable()->comment('Loan type end date');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            $table->index('loan_name');
            $table->index('has_interest');
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
        Schema::connection('bcmis2')->dropIfExists('loan_type');
    }
};

