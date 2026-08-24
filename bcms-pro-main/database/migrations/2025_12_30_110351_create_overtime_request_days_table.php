<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeRequestDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_request_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('overtime_request_id')->comment('Reference to overtime_requests table');
            $table->date('day_date')->comment('Date of the overtime day');
            $table->decimal('overtime_hours', 8, 2)->comment('Overtime hours for this day');
            $table->decimal('daily_rate', 10, 2)->nullable()->comment('Daily rate based on educational level');
            $table->decimal('amount', 12, 2)->nullable()->comment('Calculated amount (daily_rate per day)');
            $table->timestamps();

            // Foreign key
            $table->foreign('overtime_request_id')
                ->references('id')
                ->on('overtime_requests')
                ->onDelete('cascade');

            // Indexes
            $table->index('overtime_request_id');
            $table->index('day_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_request_days');
    }
}
