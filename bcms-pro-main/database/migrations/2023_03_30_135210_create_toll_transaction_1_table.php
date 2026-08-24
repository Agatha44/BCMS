<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollTransaction1Table extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('toll_transaction_1', function(Blueprint $table)
		{
			$table->integer('id')->nullable();
			$table->string('account_no', 30)->nullable();
			$table->string('vehicle_id', 50)->nullable();
			$table->string('receipt_num', 100)->nullable();
			$table->bigInteger('charged_amount')->nullable();
			$table->dateTime('created_at')->nullable();
			$table->integer('created_by')->nullable();
			$table->integer('exemption')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('toll_transaction_1');
	}

}
