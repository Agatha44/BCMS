<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExemptedTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('exempted', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('name', 100);
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
		Schema::drop('exempted');
	}

}
