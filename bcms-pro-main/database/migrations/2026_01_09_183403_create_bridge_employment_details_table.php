<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmploymentDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employment_details', function (Blueprint $table) {
            $table->id();
            $table->string('contract_type', 50)->nullable();
            $table->date('empdate')->nullable();
            $table->date('enddate')->nullable();
            $table->unsignedBigInteger('du_id')->nullable();
            $table->unsignedBigInteger('positionid')->nullable();
            $table->unsignedBigInteger('salscaleid')->nullable();
            $table->decimal('curbasicsal', 15, 2)->nullable();
            $table->unsignedBigInteger('salrangeid')->nullable();
            $table->unsignedBigInteger('gradeid')->nullable();
            $table->unsignedBigInteger('report_tono1')->nullable();
            $table->unsignedBigInteger('jobtitleid')->nullable();
            $table->date('lpromodate')->nullable();
            $table->string('confirmed', 20)->nullable();
            $table->date('dateconfirmed')->nullable();
            $table->text('description')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->unsignedBigInteger('emptype_id')->nullable();
            $table->string('cby', 50)->nullable();
            $table->timestamp('cdate')->nullable();
            $table->string('employment_place', 200)->nullable();
            $table->unsignedBigInteger('officeid')->nullable();
            $table->date('prob_enddate')->nullable();
            $table->text('prob_descr')->nullable();
            $table->unsignedBigInteger('report_tono2')->nullable();
            $table->date('transfer_date')->nullable();
            $table->timestamp('edate')->nullable();
            $table->string('eby', 50)->nullable();
            $table->string('confirmedby', 50)->nullable();
            $table->date('confirmedbydate')->nullable();
            $table->string('department_section', 200)->nullable();
            $table->date('date_of_first_appointment')->nullable();
            $table->date('date_of_current_appointment')->nullable();

            // Indexes for better performance
            $table->index('national_id');
            $table->index('emptype_id');
            $table->index('du_id');
            $table->index('positionid');
            $table->index('jobtitleid');
            $table->index('officeid');
            $table->index('report_tono1');
            $table->index('report_tono2');
            $table->index('empdate');
            $table->index('enddate');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employment_details');
    }
}

