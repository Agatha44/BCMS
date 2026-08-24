<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('last_login');
        });

    }

    public function down(): void
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
