<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeShiftsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_shifts', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('role_id');
            $table->string('shift_name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(1)->comment('Whether the shift is active');
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();

            $table->index('role_id', 'bridge_shifts_role_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_shifts');
    }
}


