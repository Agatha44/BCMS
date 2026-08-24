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
        Schema::connection('bcmis2')->create('employee_loan_repayment', function (Blueprint $table) {
            $table->id('employee_loan_repayment_id')->comment('Unique employee loan repayment record identifier');

            $table->unsignedBigInteger('employee_loan_id')->comment('Employee loan identifier (links to employee_loan.employee_loan_id)');

            $table->date('repayment_date')->comment('Date when the repayment was made/posted');
            $table->unsignedSmallInteger('payroll_month')->nullable()->comment('Payroll month for which this repayment applies (1-12)');
            $table->unsignedSmallInteger('payroll_year')->nullable()->comment('Payroll year for which this repayment applies (e.g. 2026)');

            $table->unsignedBigInteger('payroll_number')->nullable()->comment('Payroll number for which this repayment applies');
            $table->decimal('repayment_total_amount', 15, 2)->comment('Total repayment amount (principal + interest)');
            $table->decimal('repayment_principal_amount', 15, 2)->nullable()->comment('Principal portion of the repayment amount');
            $table->decimal('repayment_interest_amount', 15, 2)->nullable()->comment('Interest portion of the repayment amount');

            $table->decimal('opening_outstanding_balance_amount', 15, 2)
                ->nullable()
                ->comment('Outstanding balance before applying this repayment');

            $table->decimal('closing_outstanding_balance_amount', 15, 2)
                ->nullable()
                ->comment('Outstanding balance after applying this repayment');

            $table->enum('repayment_source', ['payroll', 'cash', 'bank_transfer', 'adjustment'])
                ->default('payroll')
                ->comment('Where this repayment came from');

            $table->string('payment_reference_number', 100)
                ->nullable()
                ->comment('Payment reference number (bank slip, receipt no, transaction id, etc.)');

            $table->string('notes', 500)->nullable()->comment('Additional notes/remarks for this repayment');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('employee_loan_id');
            $table->index('repayment_date');
            $table->index(['payroll_year', 'payroll_month'], 'idx_loan_repayment_payroll_period');
            $table->index('repayment_source');
            $table->index('payment_reference_number');
            $table->index('created_at');

            // Prevent duplicate repayments for the same loan/payroll period (when posted via payroll)
            $table->unique(
                ['employee_loan_id', 'payroll_year', 'payroll_month', 'repayment_source'],
                'uniq_loan_repayment_period_source'
            );

            // Foreign key
            $table->foreign('employee_loan_id')
                ->references('employee_loan_id')
                ->on('employee_loan')
                ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('employee_loan_repayment');
    }
};

