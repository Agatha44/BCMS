<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLaneTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('lane', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('lane_no', 50)->default('');
			$table->string('camera_ip', 50)->default('');
			$table->string('reader_ip', 50)->default('');
			$table->boolean('status')->default(1);
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('updated_by')->nullable();
			$table->string('com_port', 50)->nullable();
			$table->integer('payment_method')->nullable();
			$table->integer('reader_port')->nullable();
			$table->string('mac_address', 50)->nullable();
			$table->string('gate_ip', 100)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('lane');
	}

}
