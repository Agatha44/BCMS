<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            // PREPARATION (data build stage)
            $table->string('prepared_by')->nullable()->index();
            $table->timestamp('prepared_at')->nullable();

            // INITIATION
            $table->string('initiated_by')->nullable()->index();
            $table->timestamp('initiated_at')->nullable();

            // VERIFICATION
            $table->string('verified_by')->nullable()->index();
            $table->timestamp('verified_at')->nullable();

            // EXAMINATION
            $table->string('examined_by')->nullable()->index();
            $table->timestamp('examined_at')->nullable();

            // APPROVAL
            $table->string('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            // Drop indexes explicitly for safer rollbacks
            $table->dropIndex('payroll_runs_prepared_by_index');
            $table->dropIndex('payroll_runs_initiated_by_index');
            $table->dropIndex('payroll_runs_verified_by_index');
            $table->dropIndex('payroll_runs_examined_by_index');
            $table->dropIndex('payroll_runs_approved_by_index');

            $table->dropColumn([
                'prepared_by',
                'prepared_at',
                'initiated_by',
                'initiated_at',
                'verified_by',
                'verified_at',
                'examined_by',
                'examined_at',
                'approved_by',
                'approved_at',
            ]);
        });
    }
};

