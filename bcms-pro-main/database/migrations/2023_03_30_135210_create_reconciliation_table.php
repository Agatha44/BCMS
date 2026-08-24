<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReconciliationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('reconciliation', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('SPBILLID', 12)->nullable();
			$table->string('BILLCTRNUM', 25)->nullable();
			$table->string('PSPTRXID', 50)->nullable();
			$table->string('PAIDAMT', 30)->nullable();
			$table->string('CCY', 20)->nullable();
			$table->string('PAYREFID', 20)->nullable();
			$table->dateTime('TRXDTTM')->nullable();
			$table->string('CTRACCNUM', 20)->nullable();
			$table->string('USDPAYCHNL', 20)->nullable();
			$table->string('PSPNAME', 35)->nullable();
			$table->string('PSPCODE', 15)->nullable();
			$table->string('DPTCELLNUM', 20)->nullable();
			$table->string('DPTNAME', 20)->nullable();
			$table->string('DPTEMAILADDR', 50)->nullable();
			$table->string('REMARK', 20)->nullable();
			$table->string('error_code', 10)->nullable();
			$table->dateTime('created_at')->nullable();
			$table->string('cby', 20)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('reconciliation');
	}

}
