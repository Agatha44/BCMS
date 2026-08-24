<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRegistrationCardPathToRegistrationRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('registration_card_path')->nullable()->after('update_details')->comment('Path to uploaded registration card file');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn('registration_card_path');
        });
    }
}
