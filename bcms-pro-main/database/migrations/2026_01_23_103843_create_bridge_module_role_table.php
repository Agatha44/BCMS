<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeModuleRoleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_module_role', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->integer('role_id');
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();

            // Foreign key constraints
            $table->foreign('module_id', 'bridge_module_role_module_id_foreign')
                ->references('id')
                ->on('bridge_module')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table->foreign('role_id', 'bridge_module_role_role_id_foreign')
                ->references('id')
                ->on('roles')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            // Unique constraint to prevent duplicate module-role assignments
            $table->unique(['module_id', 'role_id'], 'bridge_module_role_unique');

            // Indexes for better performance
            $table->index('module_id');
            $table->index('role_id');
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
        Schema::connection('bcmis2')->dropIfExists('bridge_module_role');
    }
}
