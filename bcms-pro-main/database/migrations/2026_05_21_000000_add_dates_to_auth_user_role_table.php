<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDatesToAuthUserRoleTable extends Migration
{
    public function up()
    {
        Schema::table('auth_user_role', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('is_active');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down()
    {
        Schema::table('auth_user_role', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
}
