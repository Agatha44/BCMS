<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVfdZreportTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('vfd_zreport', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('znumber')->nullable();
			$table->date('received_date')->nullable();
			$table->time('received_time')->nullable();
			$table->integer('ackcode')->nullable();
			$table->string('ackmsg', 25)->nullable();
			$table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'));
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('vfd_zreport');
	}

}
