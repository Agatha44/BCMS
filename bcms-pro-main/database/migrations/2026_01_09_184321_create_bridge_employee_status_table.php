<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmployeeStatusTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employee_status', function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 50)->nullable();
            $table->string('employee_status', 50)->nullable();
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->string('cby', 50)->nullable();
            $table->timestamp('cdate')->nullable();
            $table->text('description')->nullable();
            $table->json('proposed_changes')->nullable();
            $table->integer('start_month')->nullable();
            $table->integer('start_year')->nullable();
            $table->unsignedBigInteger('es_id')->nullable();
            $table->timestamp('statusdate')->nullable();
            $table->string('verified_by', 50)->nullable();
            $table->timestamp('verify_date')->nullable();
            $table->string('approved_by', 50)->nullable();
            $table->timestamp('approve_date')->nullable();
            $table->boolean('verified')->nullable()->default(false);
            $table->boolean('approved')->nullable()->default(false);
            $table->string('previous_employee_status', 50)->nullable();

            // Indexes for better performance
            $table->index('national_id');
            $table->index('employee_status');
            $table->index('reason_id');
            $table->index('es_id');
            $table->index('statusdate');
            $table->index('verified');
            $table->index('approved');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employee_status');
    }
}

