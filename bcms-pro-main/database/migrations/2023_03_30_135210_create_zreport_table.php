<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZreportTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('zreport', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->date('date')->nullable();
			$table->time('time')->nullable();
			$table->string('vrn', 100)->nullable();
			$table->string('tin', 50)->nullable();
			$table->string('name', 200)->nullable();
			$table->string('taxoffice', 100)->nullable();
			$table->string('regid', 30)->nullable();
			$table->integer('znumber')->nullable();
			$table->string('efdserial', 30)->nullable();
			$table->date('registrationdate')->nullable();
			$table->string('user', 100)->nullable();
			$table->string('simimsi', 200)->nullable();
			$table->bigInteger('dailytotalamount')->nullable();
			$table->bigInteger('gross')->nullable();
			$table->bigInteger('corrections')->nullable();
			$table->bigInteger('discounts')->nullable();
			$table->bigInteger('surcharges')->nullable();
			$table->integer('ticketsvoid')->nullable();
			$table->integer('ticketsvoidtotal')->nullable();
			$table->integer('ticketfiscal')->nullable();
			$table->integer('ticketsnonfiscal')->nullable();
			$table->string('vatrate', 50)->nullable();
			$table->string('netamount', 100)->nullable();
			$table->string('taxamount', 100)->nullable();
			$table->integer('cashamount')->nullable();
			$table->bigInteger('emoney_amount')->nullable();
			$table->string('emoney_type', 50)->nullable();
			$table->string('cash_type', 50)->nullable();
			$table->integer('vatchangenum')->nullable();
			$table->integer('headchangenum')->nullable();
			$table->string('city', 100)->nullable();
			$table->string('mobile', 200)->nullable();
			$table->string('address', 100)->nullable();
			$table->string('ackmsg', 50)->nullable();
			$table->integer('ackcode')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('zreport');
	}

}
