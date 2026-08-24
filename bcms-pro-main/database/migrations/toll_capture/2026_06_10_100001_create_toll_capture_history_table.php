<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollCaptureHistoryTable extends Migration
{
    public function up()
    {
        Schema::create('toll_capture_history', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('toll_capture_id')->nullable();
            $table->string('plate_no', 50);
            $table->integer('lane_id')->nullable();
            $table->string('lane_number', 20)->nullable();
            $table->integer('body_type_id')->nullable();
            $table->integer('vehicle_id')->nullable();
            $table->float('amount', 10, 0)->nullable();
            $table->string('image', 500)->nullable(); // Archived capture image path on disk
            $table->integer('shift_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->string('account_no', 30)->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->integer('toll_transaction_id')->nullable();
            $table->string('receipt_num', 50)->nullable();
            $table->string('notes', 255)->nullable();
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index('plate_no');
            $table->index('toll_capture_id');
            $table->index('lane_number');
            $table->index('payment_method');
        });
    }

    public function down()
    {
        Schema::dropIfExists('toll_capture_history');
    }
}
