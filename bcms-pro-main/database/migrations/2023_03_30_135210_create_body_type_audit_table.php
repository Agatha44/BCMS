<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBodyTypeAuditTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('body_type_audit', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('previous_body_type', 25);
			$table->string('new_body_type', 25);
			$table->timestamps(6);
			$table->string('created_by', 25);
			$table->string('updated_by', 25)->nullable();
			$table->string('vehicle_id', 100)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('body_type_audit');
	}

}
