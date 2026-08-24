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
        Schema::connection('bcmis2')->create('employee_deduction', function (Blueprint $table) {
            $table->id('employee_deduction_id')->comment('Unique employee deduction record identifier');

            $table->string('employee_national_id', 50)->comment('Employee national ID (links to bridge_employee.national_id)');
            $table->unsignedBigInteger('deduction_type_id')->comment('Deduction type identifier (links to deduction_type.deduction_type_id)');

            $table->decimal('total_deduction_amount', 15, 2)->comment('Total deducted amount for this record (sum of employee + employer amounts if applicable)');

            $table->decimal('employee_contribution_percentage', 8, 2)->nullable()->comment('Employee contribution percentage used for calculation (if percentage-based)');
            $table->decimal('employee_contribution_amount', 15, 2)->nullable()->comment('Employee contribution amount');

            $table->decimal('employer_contribution_percentage', 8, 2)->nullable()->comment('Employer contribution percentage used for calculation (if percentage-based)');
            $table->decimal('employer_contribution_amount', 15, 2)->nullable()->comment('Employer contribution amount');

            $table->boolean('is_before_tax')
                ->default(false)
                ->comment('Whether this deduction reduces taxable income (pre-tax deduction)');

            $table->decimal('taxable_amount', 15, 2)->nullable()->comment('Portion of the deduction treated as taxable (if applicable in payroll rules)');
            $table->decimal('tax_free_amount', 15, 2)->nullable()->comment('Portion of the deduction treated as tax-free (if applicable in payroll rules)');

            $table->date('effective_start_date')->nullable()->comment('Date when this deduction starts applying to the employee');
            $table->date('effective_end_date')->nullable()->comment('Date when this deduction stops applying to the employee');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this employee deduction record is active');

            $table->string('notes', 500)->nullable()->comment('Additional notes/remarks for this employee deduction');

            $table->string('created_by', 50)->nullable()->comment('PF number who created');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->string('modified_by', 50)->nullable()->comment('PF number who last modified');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes for performance and data integrity
            $table->index('employee_national_id');
            $table->index('deduction_type_id');
            $table->index('is_before_tax');
            $table->index('is_active');
            $table->index('effective_start_date');
            $table->index('effective_end_date');
            $table->index('created_at');

            // Prevent duplicate deduction assignments per employee (for the same start date)
            $table->unique(['employee_national_id', 'deduction_type_id', 'effective_start_date'], 'uniq_emp_deduction_type_start_date');

            // Foreign keys
            $table->foreign('employee_national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            $table->foreign('deduction_type_id')
                ->references('deduction_type_id')
                ->on('deduction_type')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('employee_deduction');
    }
};

