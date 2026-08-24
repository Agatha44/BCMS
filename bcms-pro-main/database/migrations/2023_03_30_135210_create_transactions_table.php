<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('transactions', function(Blueprint $table)
		{
			$table->integer('gc', true);
			$table->integer('dc')->nullable();
			$table->integer('rctnum')->nullable()->index('idx_transactions_rct_num');
			$table->string('account_no', 500)->nullable();
			$table->integer('lane_id')->nullable();
			$table->integer('vehicle_id')->nullable();
			$table->integer('body_type_id')->nullable();
			$table->string('receipt_num', 50)->nullable();
			$table->string('mobile_num', 50)->nullable();
			$table->string('cust_name', 50)->nullable();
			$table->bigInteger('charged_amount')->nullable();
			$table->string('payment_type', 25)->nullable();
			$table->timestamp('created_at')->nullable()->default(DB::raw('CURRENT_TIMESTAMP'))->index('idx_transactions_created_at');
			$table->string('created_by', 50)->nullable()->index('idx_transactions_created_by');
			$table->string('trans_desc', 25)->nullable();
			$table->string('trans_source', 25)->nullable();
			$table->integer('ack_code')->nullable();
			$table->string('status', 25)->nullable();
			$table->integer('shift_id')->nullable();
			$table->dateTime('receipt_date')->nullable();
			$table->integer('receipt_type')->nullable();
			$table->integer('payment_method')->nullable();
			$table->integer('collection_office')->nullable();
			$table->string('plate_no', 20)->nullable();
			$table->integer('tin')->nullable();
			$table->string('verification_url', 200)->nullable();
			$table->dateTime('start_task')->nullable();
			$table->dateTime('end_task')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('transactions');
	}

}
