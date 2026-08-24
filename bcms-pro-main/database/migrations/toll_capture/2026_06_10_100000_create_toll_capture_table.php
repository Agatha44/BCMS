<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTollCaptureTable extends Migration
{
    public function up()
    {
        Schema::create('toll_capture', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('plate_no', 50);
            $table->integer('lane_id')->nullable();
            $table->string('lane_number', 20)->nullable();
            $table->integer('body_type_id')->nullable();
            $table->integer('vehicle_id')->nullable();
            $table->float('amount', 10, 0)->nullable();
            $table->string('image', 500)->nullable(); // Relative path/filename on disk (ANPR capture)
            $table->string('status', 20)->default('pending');
            $table->integer('shift_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['lane_number', 'status']);
            $table->index('plate_no');
        });
    }

    public function down()
    {
        Schema::dropIfExists('toll_capture');
    }
}
