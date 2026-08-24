<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollTransactionTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('toll_transaction', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->integer('shift_id')->nullable()->index('idx_toll_trans_shift_id');
			$table->string('account_no', 30)->nullable()->index('idx_toll_trans_account_no');
			$table->string('vehicle_id', 50)->nullable();
			$table->integer('body_type_id')->nullable()->index('idx_toll_trans_body_type_id');
			$table->string('plate_no', 50)->nullable()->index('idx_toll_trans_plate_no');
			$table->string('receipt_num', 100)->nullable();
			$table->bigInteger('charged_amount')->nullable();
			$table->timestamps(6);
			$table->integer('created_by')->nullable()->index('idx_toll_transaction_created_by');
			$table->string('lane_id', 5)->nullable()->index('idx_toll_trans_lane_id');
			$table->integer('rctnum')->nullable();
			$table->string('trans_type', 30)->nullable();
			$table->integer('exemption')->nullable();
			$table->integer('status')->nullable();
			$table->string('reason', 500)->nullable();
			$table->integer('updated_by')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('toll_transaction');
	}

}
