<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('payroll_transaction', function (Blueprint $table) {
            if (! Schema::connection('bcmis2')->hasColumn('payroll_transaction', 'psssf_employer_contribution')) {
                $table->decimal('psssf_employer_contribution', 15, 2)
                    ->default(0)
                    ->after('psssf_contribution');
            }
        });

        Schema::connection('bcmis2')->table('payroll_transaction_preview', function (Blueprint $table) {
            if (! Schema::connection('bcmis2')->hasColumn('payroll_transaction_preview', 'psssf_employer_contribution')) {
                $table->decimal('psssf_employer_contribution', 15, 2)
                    ->default(0)
                    ->after('psssf_contribution');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('payroll_transaction', function (Blueprint $table) {
            if (Schema::connection('bcmis2')->hasColumn('payroll_transaction', 'psssf_employer_contribution')) {
                $table->dropColumn('psssf_employer_contribution');
            }
        });

        Schema::connection('bcmis2')->table('payroll_transaction_preview', function (Blueprint $table) {
            if (Schema::connection('bcmis2')->hasColumn('payroll_transaction_preview', 'psssf_employer_contribution')) {
                $table->dropColumn('psssf_employer_contribution');
            }
        });
    }
};
