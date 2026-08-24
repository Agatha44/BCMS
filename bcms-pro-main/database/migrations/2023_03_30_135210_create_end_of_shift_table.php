<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEndOfShiftTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('end_of_shift', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('receipt_number', 50)->nullable();
			$table->timestamps(6);
			$table->dateTime('bank_date')->nullable();
			$table->string('accountant', 50)->nullable();
			$table->dateTime('shift_date')->nullable();
			$table->integer('shift_id')->nullable();
			$table->integer('status')->nullable();
			$table->integer('receipt_type')->nullable();
			$table->bigInteger('amount')->nullable();
			$table->string('bank_receipt', 100)->nullable();
			$table->dateTime('receipt_date')->nullable();
			$table->string('cancel_reason', 500)->nullable();
			$table->integer('updated_by')->nullable();
			$table->unique(['shift_id','shift_date','status'], 'end_of_shift_pk');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('end_of_shift');
	}

}
