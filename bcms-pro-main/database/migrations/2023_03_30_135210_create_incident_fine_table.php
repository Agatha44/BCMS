<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIncidentFineTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('incident_fine', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->string('nature_incident', 50)->nullable();
			$table->string('driver_name', 50)->nullable();
			$table->string('vehicle_owner', 50)->nullable();
			$table->string('plate_number', 50)->nullable();
			$table->string('payment_type', 50)->nullable();
			$table->string('phone_number', 50)->nullable();
			$table->string('police_rb', 50)->nullable();
			$table->string('owner_name', 50)->nullable();
			$table->float('amount', 10, 0)->nullable();
			$table->dateTime('incident_date')->nullable();
			$table->string('error_code', 50)->nullable();
			$table->string('t_status', 50)->nullable();
			$table->string('control_num', 50)->nullable();
			$table->dateTime('bill_exp_dt')->nullable();
			$table->dateTime('bill_gen_at')->nullable();
			$table->timestamps(6);
			$table->string('created_by', 50)->nullable();
			$table->string('email', 50)->nullable();
			$table->string('insurance_name', 50)->nullable();
			$table->string('psp_receipt_num', 50)->nullable();
			$table->string('psp_name', 50)->nullable();
			$table->string('is_cancelled', 50)->nullable();
			$table->string('bill_status', 10)->nullable();
			$table->string('ctr_acc_num', 50)->nullable();
			$table->string('usd_pay_chn', 50)->nullable();
			$table->dateTime('trx_dt_tm')->nullable();
			$table->integer('trx_id')->nullable();
			$table->string('collection_office', 50)->nullable()->default('1');
			$table->string('cancel_reason', 50)->nullable();
			$table->dateTime('bill_cancel_date')->nullable();
			$table->integer('bill_cancel_by')->nullable();
			$table->string('payer_name', 50)->nullable();
			$table->string('receipt_type', 50)->nullable()->default('2');
			$table->string('payment_method', 50)->nullable()->default('3');
			$table->string('pyr_cell_num', 15)->nullable();
			$table->string('receipt_number', 20)->nullable()->unique('incident_fine_receipt_number_uindex');
			$table->string('pay_ref_id', 25)->nullable();
			$table->integer('erp_status')->nullable();
			$table->dateTime('receipt_date')->nullable();
			$table->dateTime('payment_date')->nullable();
			$table->integer('updated_by')->nullable();
			$table->float('paid_amt', 10, 0)->nullable();
			$table->string('pyr_name', 50)->nullable();
			$table->integer('http_status')->nullable();
			$table->string('source', 200)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('incident_fine');
	}

}
