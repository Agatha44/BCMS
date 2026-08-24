<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeModuleRoleMenuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_module_role_menu', function (Blueprint $table) {
            $table->id();
            $table->integer('role_id');
            $table->unsignedBigInteger('menu_id');
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();

            // Foreign key constraints
            $table->foreign('role_id', 'bridge_module_role_menu_role_id_foreign')
                ->references('id')
                ->on('roles')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table->foreign('menu_id', 'bridge_module_role_menu_menu_id_foreign')
                ->references('id')
                ->on('bridge_module_menu')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            // Unique constraint to prevent duplicate role-menu assignments
            $table->unique(['role_id', 'menu_id'], 'bridge_module_role_menu_unique');

            // Indexes for better performance
            $table->index('role_id');
            $table->index('menu_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_module_role_menu');
    }
}
