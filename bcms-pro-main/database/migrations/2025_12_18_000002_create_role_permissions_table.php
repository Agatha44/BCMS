<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolePermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('role_permissions', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('role_id');
            $table->integer('permission_id');
            $table->boolean('is_active')->default(1);
            $table->integer('created_by');
            $table->timestamp('created_at');
            $table->integer('modified_by');
            $table->timestamp('modified_at');

            $table->foreign('role_id')->references('id')->on('roles');
            $table->foreign('permission_id')->references('id')->on('permissions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('role_permissions', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['permission_id']);
        });

        Schema::connection('bcmis2')->dropIfExists('role_permissions');
    }
}
