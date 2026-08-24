<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeModuleMenuTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_module_menu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->string('menu_name', 255);
            $table->string('menu_path', 500);
            $table->string('menu_icon', 255)->nullable();
            $table->integer('menu_order')->nullable();
            $table->text('menu_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();

            // Foreign key constraints
            $table->foreign('module_id', 'bridge_module_menu_module_id_foreign')
                ->references('id')
                ->on('bridge_module')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            // Indexes
            $table->index('module_id');
            $table->index('menu_name');
            $table->index('menu_path');
            $table->index('menu_order');
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
        Schema::connection('bcmis2')->dropIfExists('bridge_module_menu');
    }
}
