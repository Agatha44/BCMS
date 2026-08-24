<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot line items for an employee in a payroll period (earnings, deductions, benefits, etc.).
     */
    public function up(): void
    {
        Schema::connection('bcmis2')->create('employee_payroll_items', function (Blueprint $table) {
            $table->id()->comment('Unique payroll item row identifier');

            $table->string('national_id', 50)->comment('Employee national ID (links to bridge_employee.national_id)');
            $table->unsignedTinyInteger('payroll_month')->comment('Calendar month (1–12) for this payroll run');
            $table->unsignedSmallInteger('payroll_year')->comment('Calendar year for this payroll run');

            $table->string('item_type', 50)->comment('Category of line item (e.g. earning, deduction, benefit, loan, arrears)');
            $table->string('source_table', 128)->nullable()->comment('Source table name for the originating record');
            $table->unsignedBigInteger('source_id')->nullable()->comment('Primary key of the row in source_table');
            $table->string('type_table', 128)->nullable()->comment('Optional type lookup table (e.g. deduction_type)');
            $table->unsignedBigInteger('type_id')->nullable()->comment('Primary key in type_table');

            $table->string('name', 255)->comment('Display label for the item on payslip / reports');

            $table->decimal('amount', 15, 2)->default(0)->comment('Total monetary amount for this line');
            $table->decimal('taxed_amount', 15, 2)->nullable()->comment('Portion subject to tax rules');
            $table->decimal('taxfree_amount', 15, 2)->nullable()->comment('Portion treated as tax-free');

            $table->decimal('employee_amount', 15, 2)->nullable()->comment('Employee-side monetary component');
            $table->decimal('employer_amount', 15, 2)->nullable()->comment('Employer-side monetary component');
            $table->decimal('employee_percent', 10, 4)->nullable()->comment('Employee percentage used in calculation');
            $table->decimal('employer_percent', 10, 4)->nullable()->comment('Employer percentage used in calculation');
            $table->unsignedBigInteger('payroll_run_id')
                ->nullable()
                ->comment('Reference to payroll_runs table');

            $table->boolean('is_void')->default(false)->comment('Whether this item is voided');
            $table->string('void_reason', 255)->nullable()->comment('Reason for voiding the item');
            $table->timestamp('void_at')->nullable()->comment('Timestamp when the item was voided');
            $table->unsignedBigInteger('void_by')->nullable()->comment('User who voided the item');

            $table->json('meta')->nullable()->comment('Structured extra data (rates, formulas, references)');

            $table->timestamp('created_at')->useCurrent()->comment('When this snapshot row was written');

            $table->index(['national_id', 'payroll_year', 'payroll_month'], 'idx_emp_payroll_items_emp_period');
            $table->index(['source_table', 'source_id'], 'idx_emp_payroll_items_source');
            $table->index(['type_table', 'type_id'], 'idx_emp_payroll_items_type');
            $table->index('item_type');
            $table->index('payroll_run_id', 'idx_payroll_items_run');

            $table->foreign('national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            $table->foreign('payroll_run_id')
                ->references('id')
                ->on('payroll_runs')
                ->onDelete('cascade');

            $table->unique([
                    'payroll_run_id',
                    'national_id',
                    'item_type',
                    'source_table',
                    'source_id',
                ], 'uniq_payroll_items_run_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('employee_payroll_items');
    }
};
