<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollFeeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('toll_fee', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->bigInteger('lane_id');
			$table->string('account_vehicle_id', 50);
			$table->float('amount', 10, 0);
			$table->boolean('status')->default(1);
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('toll_fee');
	}

}
