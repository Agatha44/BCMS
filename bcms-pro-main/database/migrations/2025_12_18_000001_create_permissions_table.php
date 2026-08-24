<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('permissions', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('controller', 150);
            $table->string('permission', 100);
            $table->string('route', 255);
            $table->boolean('is_active')->default(1);
            $table->integer('created_by');
            $table->timestamp('created_at');
            $table->integer('modified_by');
            $table->timestamp('modified_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('permissions');
    }
}
