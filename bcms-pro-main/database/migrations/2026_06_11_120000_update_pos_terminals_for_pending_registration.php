<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePosTerminalsForPendingRegistration extends Migration
{
    public function up()
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->string('lane_number', 20)->nullable()->change();
            $table->bigInteger('registered_by')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('pos_terminals', function (Blueprint $table) {
            $table->string('lane_number', 20)->nullable(false)->change();
            $table->bigInteger('registered_by')->nullable(false)->change();
        });
    }
}
