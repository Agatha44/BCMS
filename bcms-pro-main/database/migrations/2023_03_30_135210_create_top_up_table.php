<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTopUpTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('top_up', function(Blueprint $table)
		{
			$table->bigInteger('id', true)->comment('ID');
			$table->string('account_no', 50)->default('0')->comment('PYID');
			$table->string('bill_desc', 500)->nullable();
			$table->float('bill_amount', 10, 0);
			$table->dateTime('bill_exp_dt')->nullable()->comment('BILL EXPIRED DATE');
			$table->string('contr_num', 50)->nullable()->comment('CONTROL NUM');
			$table->integer('bill_gen_by')->nullable()->comment('BILL APPROVED BY');
			$table->dateTime('bill_gen_at')->nullable()->comment('BILL GENERATED DT');
			$table->integer('bill_cancel_by')->nullable()->comment('DEACTIVATED BY');
			$table->dateTime('bill_cancel_date')->nullable()->comment('DEACTIVATED AT');
			$table->string('cancel_reason', 100)->nullable();
			$table->integer('is_cancelled')->nullable()->comment('IS DEACTIVATED');
			$table->string('trx_id', 500)->nullable()->comment('TRANSACTION ID');
			$table->dateTime('trx_dt_tm')->nullable()->comment('TRANSACTION DT');
			$table->string('usd_pay_chn', 100)->nullable()->comment('USED PAYMENT CHANNEL');
			$table->string('pyr_cell_num', 15)->nullable();
			$table->string('psp_receipt_num', 100)->nullable()->comment('PAYMENT SERVICE PROVIDER RECEIPT NUM');
			$table->string('psp_name', 100)->nullable()->comment('PAYMENT SERVICE PROVIDER NAME');
			$table->string('ctr_acc_num', 100)->nullable()->comment('CREDIT ACCOUNT NUM');
			$table->string('error_code', 50)->nullable();
			$table->string('t_status', 50)->nullable();
			$table->string('bill_status', 5)->nullable();
			$table->dateTime('updated_at')->nullable();
			$table->string('receipt_number', 20)->nullable()->unique('top_up_receipt_number_uindex');
			$table->string('pay_ref_id', 25)->nullable();
			$table->string('receipt_type', 25)->nullable()->default('4');
			$table->string('collection_office', 25)->nullable()->default('2');
			$table->integer('erp_status')->nullable();
			$table->dateTime('receipt_date')->nullable();
			$table->dateTime('payment_date')->nullable();
			$table->float('paid_amt', 10, 0)->nullable();
			$table->string('payer_name', 50)->nullable();
			$table->string('updated_by', 50)->nullable();
			$table->integer('tin')->nullable();
			$table->integer('http_status')->nullable();
			$table->string('source', 200)->nullable();
			$table->unique(['account_no','psp_receipt_num'], 'account_no_psp_receipt_num');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('top_up');
	}

}
