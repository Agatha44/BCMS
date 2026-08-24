<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollBundlesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('toll_bundles', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('bundle_description', 150)->nullable();
			$table->integer('duration')->nullable();
			$table->timestamps(6);
			$table->integer('created_by')->nullable();
			$table->dateTime('updated_by')->nullable();
			$table->integer('status')->nullable();
			$table->string('sw_desc', 20)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('toll_bundles');
	}

}
