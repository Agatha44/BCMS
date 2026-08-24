<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVarianceTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('variance', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->date('shift_date')->nullable();
			$table->integer('shift')->nullable()->index('variance_shift_id_fk');
			$table->bigInteger('amount')->nullable();
			$table->integer('created_by')->nullable();
			$table->timestamps(6);
			$table->integer('status')->nullable()->default(1);
			$table->integer('updated_by')->nullable();
			$table->string('reason', 500)->nullable();
			$table->unique(['shift_date','shift','status'], 'unique_index');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('variance');
	}

}
