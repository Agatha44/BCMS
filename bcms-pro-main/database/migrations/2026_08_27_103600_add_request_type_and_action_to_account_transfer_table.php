<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->string('request_type', 50)->nullable()->after('narration');
            $table->string('action', 50)->nullable()->after('request_type');
        });
    }

    public function down(): void
    {
        Schema::table('account_transfer', function (Blueprint $table) {
            $table->dropColumn(['request_type', 'action']);
        });
    }
};
