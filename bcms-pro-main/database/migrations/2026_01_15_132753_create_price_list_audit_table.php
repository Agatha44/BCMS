<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceListAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('price_list_audit', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('price_list_id')->comment('Reference to price_list table');
            $table->integer('previous_body_type_id')->nullable();
            $table->integer('new_body_type_id')->nullable();
            $table->float('previous_amount', 10, 0)->nullable();
            $table->float('new_amount', 10, 0)->nullable();
            $table->float('previous_daily_bundle_amount', 10)->nullable();
            $table->float('new_daily_bundle_amount', 10)->nullable();
            $table->float('previous_weekly_bundle_amount', 10)->nullable();
            $table->float('new_weekly_bundle_amount', 10)->nullable();
            $table->float('previous_monthly_bundle_amount', 10)->nullable();
            $table->float('new_monthly_bundle_amount', 10)->nullable();
            $table->boolean('previous_status')->nullable();
            $table->boolean('new_status')->nullable();
            $table->string('action', 50)->comment('Action: created, updated, deleted');
            $table->integer('updated_by')->nullable()->comment('User who made the change');
            $table->timestamps(6);
            
            // Add index for better query performance
            $table->index('price_list_id');
            $table->index('updated_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('price_list_audit');
    }
}
