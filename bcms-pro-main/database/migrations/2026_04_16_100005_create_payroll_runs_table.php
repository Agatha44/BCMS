<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll processing runs/batches.
     * A run produces many payroll_items for a given period.
     */
    public function up(): void
    {
        Schema::connection('bcmis2')->create('payroll_runs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedTinyInteger('payroll_month');
            $table->unsignedSmallInteger('payroll_year');

            $table->string('payroll_number', 20)->nullable();

            // initiated -> verified -> approved -> posting | failed | cancelled
            $table->string('status', 30)->default('initiated');

            // Indexes
            $table->index(['payroll_year', 'payroll_month'], 'idx_payroll_runs_period');
            $table->index(['payroll_number'], 'idx_payroll_runs_number');
            $table->index(['status'], 'idx_payroll_runs_status');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->dropIfExists('payroll_runs');
    }
};

