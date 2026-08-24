<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmployeeAcademicTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employee_academic', function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 50)->nullable();
            $table->unsignedBigInteger('edulevel_id')->nullable();
            $table->string('institute_name', 200)->nullable();
            $table->string('area_of_study', 200)->nullable();
            $table->integer('startyear')->nullable();
            $table->integer('endyear')->nullable();
            $table->string('award', 200)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('certificate_no', 100)->nullable();
            $table->unsignedBigInteger('ad_id')->nullable();
            $table->string('cby', 50)->nullable();
            $table->timestamp('cdate')->nullable();
            $table->string('eby', 50)->nullable();
            $table->timestamp('edate')->nullable();

            // Indexes for better performance
            $table->index('national_id');
            $table->index('edulevel_id');
            $table->index('country_id');
            $table->index('ad_id');
            $table->index('startyear');
            $table->index('endyear');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employee_academic');
    }
}

