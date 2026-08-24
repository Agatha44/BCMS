<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountVehicleTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('account_vehicle', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('account_id', 50)->default('');
			$table->integer('vehicle_id');
			$table->integer('status')->default(1);
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
			$table->unique(['account_id','vehicle_id'], 'account_id_vehicle_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('account_vehicle');
	}

}
