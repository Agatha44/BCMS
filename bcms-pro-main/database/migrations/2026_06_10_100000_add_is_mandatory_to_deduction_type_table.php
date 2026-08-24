<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('deduction_type', function (Blueprint $table) {
            if (! Schema::connection('bcmis2')->hasColumn('deduction_type', 'is_mandatory')) {
                $table->boolean('is_mandatory')
                    ->default(false)
                    ->after('is_active')
                    ->comment('When true, auto-assigned to all active employees');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('deduction_type', function (Blueprint $table) {
            if (Schema::connection('bcmis2')->hasColumn('deduction_type', 'is_mandatory')) {
                $table->dropColumn('is_mandatory');
            }
        });
    }
};
