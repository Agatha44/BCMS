<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeRatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('educational_levels_id')->comment('Educational level ID');
            $table->integer('rate')->comment('Overtime Rate');
            $table->boolean('is_active')->default(1)->comment('Whether the Overtime Rate is active');
            $table->integer('created_by')->comment('User ID who created the record');
            $table->timestamp('created_at')->comment('Created timestamp');
            $table->integer('modified_by')->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->comment('Last modification timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_rates');
    }
}
