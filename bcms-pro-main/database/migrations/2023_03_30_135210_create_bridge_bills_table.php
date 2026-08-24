<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeBillsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('bridge_bills', function(Blueprint $table)
		{
			$table->bigInteger('id', true);
			$table->string('bill_desc', 500)->nullable();
			$table->float('bill_amount', 10, 0)->nullable();
			$table->dateTime('bill_exp_dt')->nullable();
			$table->string('contr_num', 50)->nullable();
			$table->integer('bill_gen_by')->nullable();
			$table->dateTime('bill_gen_at')->nullable();
			$table->integer('bill_cancel_by')->nullable();
			$table->dateTime('bill_cancel_date')->nullable();
			$table->string('cancel_reason', 100)->nullable();
			$table->integer('is_cancelled')->nullable();
			$table->string('trx_id', 500)->nullable();
			$table->dateTime('trx_dt_tm')->nullable();
			$table->string('usd_pay_chn', 100)->nullable();
			$table->string('pyr_cell_num', 15)->nullable();
			$table->string('psp_receipt_num', 100)->nullable();
			$table->string('psp_name', 100)->nullable();
			$table->string('ctr_acc_num', 100)->nullable();
			$table->string('error_code', 50)->nullable();
			$table->string('t_status', 50)->nullable();
			$table->string('bill_status', 5)->nullable();
			$table->dateTime('updated_at')->nullable();
			$table->string('receipt_number', 20)->nullable();
			$table->string('pay_ref_id', 25)->nullable();
			$table->string('receipt_type', 25)->nullable();
			$table->string('collection_office', 25)->nullable();
			$table->integer('erp_status')->nullable();
			$table->dateTime('receipt_date')->nullable();
			$table->dateTime('payment_date')->nullable();
			$table->float('paid_amt', 10, 0)->nullable();
			$table->string('payer_name', 50)->nullable();
			$table->string('updated_by', 50)->nullable();
			$table->date('shift_date')->nullable();
			$table->integer('shift_id')->nullable();
			$table->integer('phone_number')->nullable();
			$table->integer('bill_source')->nullable();
			$table->string('dist_param', 150)->nullable();
			$table->integer('bundle_id')->nullable();
			$table->integer('http_status')->nullable();
			$table->string('source', 200)->nullable();
			$table->unique(['shift_date','shift_id','bill_status','dist_param','psp_receipt_num'], 'bill_validation');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('bridge_bills');
	}

}
