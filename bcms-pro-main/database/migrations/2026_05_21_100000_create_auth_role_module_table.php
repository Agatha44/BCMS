<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuthRoleModuleTable extends Migration
{
    public function up()
    {
        Schema::connection('bcmis2')->create('auth_role_module', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('auth_role_id');
            $table->unsignedBigInteger('module_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('modified_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->foreign('module_id', 'auth_role_module_module_id_foreign')
                ->references('id')
                ->on('bridge_module')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table->unique(['auth_role_id', 'module_id'], 'auth_role_module_unique');
            $table->index('auth_role_id');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('auth_role_module');
    }
}
