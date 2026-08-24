<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGepgreconTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('gepgrecon', function(Blueprint $table)
		{
			$table->string('BILLID', 25);
			$table->string('PAIDAMT', 50)->default('');
			$table->string('CONTROLNO', 25);
			$table->string('PAYERNAME', 100);
			$table->string('PAYEREMAIL', 100);
			$table->string('PAYERCELLNO', 25);
			$table->string('PSPREFID', 100);
			$table->string('PAYREFID', 100);
			$table->dateTime('TXNDTTM')->nullable();
			$table->string('PACCNO', 50);
			$table->string('PBANK', 100);
			$table->string('REMARKS', 100);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('gepgrecon');
	}

}
