<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->create('payroll_erms_executions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->string('execution_type', 50)
                ->comment('miscellaneous, net_pay_payable, payable, …');
            $table->string('bank_batch', 50)->default('')
                ->comment('Net-pay bank batch (crdb, nbc, nmb, other_banks); empty for misc / monolithic payable');
            $table->string('source_ref', 120);
            $table->unsignedTinyInteger('status')
                ->default(0)
                ->comment('0 pending, 1 success, 2 failed');
            $table->string('erms_reference', 120)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('payroll_run_id')
                ->references('id')
                ->on('payroll_runs')
                ->cascadeOnDelete();

            $table->unique(
                ['payroll_run_id', 'execution_type', 'bank_batch'],
                'uq_payroll_erms_executions_run_type_batch'
            );
            $table->index(['payroll_run_id', 'status'], 'idx_payroll_erms_executions_run_status');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('payroll_erms_executions');
    }
};
