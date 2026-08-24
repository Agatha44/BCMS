<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePriceListTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('price_list', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('body_type_id')->default(0);
			$table->float('amount', 10, 0)->default(0);
			$table->float('daily_bundle_amount', 10)->nullable();
			$table->float('weekly_bundle_amount', 10)->nullable();
			$table->float('monthly_bundle_amount', 10)->nullable();
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
			$table->boolean('status')->nullable()->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('price_list');
	}

}
