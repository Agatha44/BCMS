<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundleSubscriptionPassageTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('bundle_subscription_passage', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('card_number', 150)->nullable();
			$table->integer('lane_id')->nullable();
			$table->dateTime('arrival_time')->nullable();
			$table->dateTime('clearance_time')->nullable();
			$table->timestamps(6);
			$table->integer('created_by')->nullable();
			$table->integer('updated_by')->nullable();
			$table->integer('shift_id')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('bundle_subscription_passage');
	}

}
