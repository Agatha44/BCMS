<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->create('payroll_runs_history', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('payroll_run_id')->comment('Reference to payroll_runs table');

            $table->string('action', 50)->comment('Action performed (Prepared, Initiated, Verified, Examined, Approved, Posted, Rejected, Cancelled, Updated)');
            $table->string('status', 30)->comment('Status after this action');
            $table->string('workflow_status', 50)->nullable()->comment('High-level workflow status after this action');

            $table->string('performed_by', 50)->comment('PF number of employee who performed the action (reference to bridge_employee.pfno)');
            $table->string('performed_by_role', 100)->nullable()->comment('Role name of the user at time of action');
            $table->text('comment')->nullable()->comment('Comment or note for this action');

            $table->timestamp('created_at')->useCurrent()->comment('Timestamp when action was performed');

            $table->foreign('payroll_run_id')
                ->references('id')
                ->on('payroll_runs')
                ->onDelete('cascade');

            $table->index('payroll_run_id', 'idx_payroll_runs_history_run_id');
            $table->index('performed_by', 'idx_payroll_runs_history_performed_by');
            $table->index('created_at', 'idx_payroll_runs_history_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('payroll_runs_history');
    }
};

