<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCancelledDetectionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('cancelled_detection', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('plate_no', 50)->nullable();
			$table->integer('user_id')->nullable();
			$table->integer('body_type_id')->nullable();
			$table->integer('lane_id')->nullable();
			$table->integer('shift_id')->nullable();
			$table->float('amount', 10, 0)->nullable();
			$table->dateTime('created_at')->nullable();
			$table->string('created_by', 50)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('cancelled_detection');
	}

}
