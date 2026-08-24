<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPfNumberToAuthUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->string('pf_number', 50)->nullable()->after('nida')->comment('Employee PF number');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('auth_user', function (Blueprint $table) {
            $table->dropColumn('pf_number');
        });
    }
}
