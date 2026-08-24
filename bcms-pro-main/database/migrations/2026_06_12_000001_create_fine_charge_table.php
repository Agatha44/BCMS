<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fine_charge')) {
            return;
        }

        Schema::create('fine_charge', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('payer_name', 50)->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->string('driver_name', 50)->nullable();
            $table->date('charge_date')->nullable();
            $table->string('charge_description', 200)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->float('amount', 10, 0)->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('t_status', 50)->nullable();
            $table->string('control_num', 50)->nullable();
            $table->string('psp_receipt_num', 50)->nullable();
            $table->string('psp_name', 50)->nullable();
            $table->string('is_cancelled', 50)->nullable();
            $table->string('bill_status', 10)->nullable();
            $table->string('ctr_acc_num', 50)->nullable();
            $table->string('receipt_number', 50)->nullable()->unique('fine_charge_receipt_number_uindex');
            $table->string('usd_pay_chn', 50)->nullable();
            $table->dateTime('trx_dt_tm')->nullable();
            $table->integer('trx_id')->nullable();
            $table->string('cancel_reason', 50)->nullable();
            $table->dateTime('bill_cancel_date')->nullable();
            $table->integer('bill_cancel_by')->nullable();
            $table->dateTime('bill_exp_dt')->nullable();
            $table->string('pyr_cell_num', 20)->nullable();
            $table->dateTime('bill_gen_at')->nullable();
            $table->timestamps(6);
            $table->string('created_by', 50)->nullable();
            $table->string('receipt_type', 50)->nullable()->default('1');
            $table->integer('collection_office')->nullable()->default(1);
            $table->integer('payment_method')->nullable()->default(3);
            $table->string('pay_ref_id', 25)->nullable();
            $table->integer('erp_status')->nullable();
            $table->dateTime('receipt_date')->nullable();
            $table->dateTime('payment_date')->nullable();
            $table->float('paid_amt', 10, 0)->nullable();
            $table->string('pyr_name', 50)->nullable();
            $table->integer('http_status')->nullable();
            $table->string('source', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_charge');
    }
};
