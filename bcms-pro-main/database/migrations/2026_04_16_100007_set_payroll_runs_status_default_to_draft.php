<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });
    }

    public function down(): void
    {
        Schema::connection('bcmis2')->table('payroll_runs', function (Blueprint $table) {
            $table->string('status', 30)->default('initiated')->change();
        });
    }
};

