<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyOwnerFieldsInRegistrationRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            // Modify owner_name and owner_phone to allow NULL values for update requests
            $table->string('owner_name')->nullable()->change();
            $table->string('owner_phone')->nullable()->change();
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
            // Revert back to NOT NULL constraints
            $table->string('owner_name')->nullable(false)->change();
            $table->string('owner_phone')->nullable(false)->change();
        });
    }
}
