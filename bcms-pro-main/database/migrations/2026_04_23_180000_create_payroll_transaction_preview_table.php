<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->create('payroll_transaction_preview', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Link to payroll run
            $table->unsignedBigInteger('payroll_run_id');

            $table->string('national_id', 50);
            $table->unsignedTinyInteger('payroll_month');
            $table->unsignedSmallInteger('payroll_year');

            $table->string('payroll_number', 20)->nullable();

            // Employee snapshot fields (denormalized)
            $table->string('pf_number', 50)->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('account_number', 50)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('scheme_id')->nullable();

            // Core earnings
            $table->decimal('basic_salary', 15, 2)->default(0);

            // Arrears
            $table->decimal('total_arrears', 15, 2)->default(0);

            // Aggregates
            $table->decimal('total_benefits', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_loans', 15, 2)->default(0);

            // Tax computation
            $table->decimal('gross_pay', 15, 2)->default(0);
            $table->decimal('taxable_pay', 15, 2)->default(0);
            $table->decimal('paye', 15, 2)->default(0);

            // Final result
            $table->decimal('net_pay', 15, 2)->default(0);

            // Keep same lifecycle fields as final transactions for compatibility
            $table->integer('erms_status')->default(0); // 0: pending, 1: posted, 2: failed
            $table->timestamp('erms_submitted_at')->nullable();
            $table->string('erms_reference', 100)->nullable();

            // Preview metadata
            $table->timestamp('generated_at')->nullable();
            $table->string('generated_by', 50)->nullable();

            // Indexes
            $table->index(['payroll_run_id'], 'idx_payroll_tx_preview_run');
            $table->index(['bank_id'], 'idx_payroll_tx_preview_bank_id');
            $table->index(['department_id'], 'idx_payroll_tx_preview_department_id');
            $table->index(['scheme_id'], 'idx_payroll_tx_preview_scheme_id');

            // Helpful period lookup (matches final table pattern)
            $table->index(['national_id', 'payroll_year', 'payroll_month'], 'idx_payroll_tx_preview_emp_period');

            // Foreign keys
            $table->foreign('payroll_run_id')
                ->references('id')
                ->on('payroll_runs')
                ->onDelete('cascade');

            $table->foreign('national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            // Prevent duplicates per run per employee
            $table->unique(['payroll_run_id', 'national_id'], 'uniq_payroll_tx_preview_run_emp');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('payroll_transaction_preview');
    }
};

