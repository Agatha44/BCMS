<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReceiptAuditTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('receipt_audit', function(Blueprint $table)
		{
			$table->integer('id')->nullable();
			$table->integer('gc')->nullable();
			$table->string('reason', 100)->nullable();
			$table->string('receipt_num', 50)->nullable();
			$table->integer('created_by')->nullable();
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
		Schema::drop('receipt_audit');
	}

}
