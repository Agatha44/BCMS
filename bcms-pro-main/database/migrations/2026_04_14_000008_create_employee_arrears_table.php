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
        Schema::connection('bcmis2')->create('employee_arrears', function (Blueprint $table) {
            $table->id('employee_arrears_id')->comment('Unique employee arrears record identifier');

            $table->string('employee_national_id', 50)->comment('Employee national ID (links to bridge_employee.national_id)');
            $table->unsignedBigInteger('arrears_reason_id')->comment('Arrears reason identifier (links to arrears_reasons.arrears_reason_id)');

            $table->unsignedSmallInteger('payroll_month')
                ->nullable()
                ->comment('Payroll month to which these arrears apply (1-12)');

            $table->unsignedSmallInteger('payroll_year')
                ->nullable()
                ->comment('Payroll year to which these arrears apply (e.g. 2026)');

            $table->string('payroll_number', 20)
                ->nullable()
                ->comment('Payroll number/period key (if used by payroll engine)');

            $table->decimal('arrears_amount', 15, 2)->comment('Main arrears amount');
            $table->decimal('gross_amount', 15, 2)->nullable()->comment('Gross arrears amount (before deductions/tax)');
            $table->decimal('net_amount', 15, 2)->nullable()->comment('Net arrears amount (after deductions/tax)');

            $table->decimal('taxable_amount', 15, 2)->nullable()->comment('Taxable portion of the arrears amount');
            $table->decimal('tax_free_amount', 15, 2)->nullable()->comment('Non-taxable portion of the arrears amount');

            // Common overtime-style arrears fields (optional)
            $table->decimal('overtime_days', 10, 2)->nullable()->comment('Overtime days/hours basis used for overtime arrears (if applicable)');
            $table->decimal('overtime_rate', 15, 4)->nullable()->comment('Overtime rate used for overtime arrears (if applicable)');
            $table->decimal('overtime_gross_amount', 15, 2)->nullable()->comment('Overtime gross amount (if applicable)');
            $table->decimal('overtime_net_amount', 15, 2)->nullable()->comment('Overtime net amount (if applicable)');

            $table->string('workflow_status', 20)
                ->default('pending')
                ->comment('Workflow status for this arrears record');

            $table->string('payment_status', 20)
                ->default('unpaid')
                ->comment('Payment status for this arrears record');

            $table->date('arrears_date')->nullable()->comment('Date when arrears were created/effective');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this arrears record is active');

            $table->string('notes', 500)->nullable()->comment('Additional notes/remarks for this arrears record');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('verified_by')->nullable()->comment('User ID who verified the record');
            $table->timestamp('verified_at')->nullable()->comment('Verification timestamp');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('User ID who approved the record');
            $table->timestamp('approved_at')->nullable()->comment('Approval timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('employee_national_id');
            $table->index('arrears_reason_id');
            $table->index(['payroll_year', 'payroll_month'], 'idx_employee_arrears_payroll_period');
            $table->index('payroll_number');
            $table->index('workflow_status');
            $table->index('payment_status');
            $table->index('arrears_date');
            $table->index('is_active');
            $table->index('created_at');

            $table->unique(
                ['employee_national_id', 'arrears_reason_id', 'payroll_year', 'payroll_month'],
                'unique_employee_arrears_period'
            );

            // Foreign keys
            $table->foreign('employee_national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            $table->foreign('arrears_reason_id')
                ->references('arrears_reason_id')
                ->on('arrears_reasons')
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
        Schema::connection('bcmis2')->dropIfExists('employee_arrears');
    }
};

