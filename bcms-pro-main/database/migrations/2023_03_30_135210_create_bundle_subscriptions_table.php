<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundleSubscriptionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('bundle_subscriptions', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('account_id')->nullable();
			$table->integer('bill_id')->nullable();
			$table->integer('vehicle_id')->nullable();
			$table->dateTime('start_date')->nullable();
			$table->dateTime('expire_date')->nullable();
			$table->integer('bundle_id')->nullable();
			$table->integer('status')->nullable()->default(0);
			$table->timestamps(6);
			$table->integer('created_by')->nullable();
			$table->integer('updated_by')->nullable();
			$table->string('reason', 200)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('bundle_subscriptions');
	}

}
