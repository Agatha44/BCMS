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
        Schema::connection('bcmis2')->create('employee_loan', function (Blueprint $table) {
            $table->id('employee_loan_id')->comment('Unique employee loan record identifier');

            $table->string('employee_national_id', 50)->comment('Employee national ID (links to bridge_employee.national_id)');
            $table->unsignedBigInteger('loan_type_id')->comment('Loan type identifier (links to loan_type.loan_type_id)');

            $table->string('loan_reference_number', 100)
                ->nullable()
                ->comment('External/issued loan reference number (if any)');

            $table->date('loan_issue_date')->nullable()->comment('Date when the loan was issued/disbursed');
            $table->date('repayment_start_date')->nullable()->comment('Date when loan repayment starts');

            $table->decimal('principal_amount', 15, 2)->comment('Original loan principal amount');

            $table->decimal('total_interest_amount', 15, 2)
                ->nullable()
                ->comment('Total interest amount for the full loan period (if applicable)');

            $table->unsignedInteger('repayment_period_months')
                ->comment('Repayment duration in months');

            $table->decimal('monthly_principal_amount', 15, 2)
                ->nullable()
                ->comment('Monthly principal repayment amount (excluding interest)');

            $table->decimal('monthly_interest_amount', 15, 2)
                ->nullable()
                ->comment('Monthly interest repayment amount');

            $table->decimal('monthly_total_repayment_amount', 15, 2)
                ->comment('Monthly total repayment amount (principal + interest)');

            $table->decimal('total_repaid_amount', 15, 2)
                ->default(0)
                ->comment('Total amount repaid so far');

            $table->decimal('outstanding_balance_amount', 15, 2)
                ->nullable()
                ->comment('Current outstanding balance amount');

            $table->enum('loan_status', ['active', 'completed', 'cancelled', 'suspended'])
                ->default('active')
                ->comment('Current loan status');

            $table->date('effective_start_date')->nullable()->comment('Date when this loan becomes effective in payroll deductions');
            $table->date('effective_end_date')->nullable()->comment('Date when this loan stops being deducted from payroll');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this employee loan record is active');

            $table->string('notes', 500)->nullable()->comment('Additional notes/remarks for this employee loan');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('employee_national_id');
            $table->index('loan_type_id');
            $table->index('loan_issue_date');
            $table->index('loan_status');
            $table->index('is_active');
            $table->index('created_at');

            // Foreign keys
            $table->foreign('employee_national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            $table->foreign('loan_type_id')
                ->references('loan_type_id')
                ->on('loan_type')
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
        Schema::connection('bcmis2')->dropIfExists('employee_loan');
    }
};

