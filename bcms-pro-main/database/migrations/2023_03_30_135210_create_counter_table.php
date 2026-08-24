<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCounterTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('counter', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->dateTime('open_counter')->nullable();
			$table->dateTime('close_counter')->nullable();
			$table->integer('user_id');
			$table->integer('lane_id');
			$table->integer('shift_id')->nullable();
			$table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
			$table->integer('payment_method')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('counter');
	}

}
