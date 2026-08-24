<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_engine_definitions', function (Blueprint $table) {
            $table->text('query')->nullable()->after('handler');
        });
    }

    public function down(): void
    {
        Schema::table('report_engine_definitions', function (Blueprint $table) {
            $table->dropColumn('query');
        });
    }
};
