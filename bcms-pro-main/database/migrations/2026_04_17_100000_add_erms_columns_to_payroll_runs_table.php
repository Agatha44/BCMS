<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            $table->unsignedTinyInteger('erms_status')
                ->default(0)
                ->comment('ERMS submission status: 0 not sent, 1 sent, 2 failed');
            $table->timestamp('erms_submitted_at')->nullable();
            $table->string('erms_reference', 100)->nullable();

            $table->index(['erms_status'], 'idx_payroll_runs_erms_status');
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_runs_erms_status');
            $table->dropColumn(['erms_status', 'erms_submitted_at', 'erms_reference']);
        });
    }
};
