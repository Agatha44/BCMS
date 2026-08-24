<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgePositionTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_position_type', function (Blueprint $table) {
            $table->id();
            $table->string('positionname', 200)->nullable();
            $table->unsignedBigInteger('salscaleid')->nullable();
            $table->string('cuser', 50)->nullable();
            $table->timestamp('cdate')->nullable();
            $table->decimal('lperdiem', 15, 2)->nullable();
            $table->decimal('aperdiem', 15, 2)->nullable();
            $table->decimal('outfit', 15, 2)->nullable();
            $table->unsignedBigInteger('positionid')->nullable();
            $table->string('lcurrency', 10)->nullable();
            $table->string('acurrency', 10)->nullable();
            $table->unsignedBigInteger('reportsto')->nullable();
            $table->unsignedBigInteger('reportschief')->nullable();
            $table->unsignedBigInteger('reportsto_level2')->nullable();
            $table->decimal('trasfer_allowed_weight', 15, 2)->nullable();
            $table->decimal('transfer_weight_rate', 15, 2)->nullable();
            $table->decimal('transfer_substance_rate_spouce', 15, 2)->nullable();
            $table->decimal('transfer_substance_rate_child', 15, 2)->nullable();
            $table->integer('transfer_subst_allowed_days')->nullable();
            $table->unsignedBigInteger('rankid')->nullable();

            // Indexes for better performance
            $table->index('positionid');
            $table->index('salscaleid');
            $table->index('rankid');
            $table->index('reportsto');
            $table->index('reportschief');
            $table->index('reportsto_level2');
            $table->index('positionname');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_position_type');
    }
}
