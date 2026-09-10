<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->date('request_date')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->dropColumn('request_date');
        });
    }
};
