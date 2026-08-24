<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmployeeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employee', function (Blueprint $table) {
            $table->string('national_id', 50)->primary();
            $table->string('pfno', 50)->nullable();
            $table->string('fname', 100);
            $table->string('mname', 100)->nullable();
            $table->string('sname', 100);
            $table->string('gender', 20)->nullable();
            $table->string('title', 50)->nullable();
            $table->date('dob')->nullable();
            $table->string('maritalstatus', 20)->nullable();
            $table->string('domicile', 100)->nullable();
            $table->string('employment_place', 200)->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('passport_no', 50)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->unsignedBigInteger('educational_level_id')->nullable()->comment('Reference to educational_levels table');
            $table->unsignedBigInteger('scheme_id')->nullable();
            $table->unsignedBigInteger('emptype_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('account_no', 50)->nullable();
            $table->string('cby', 50)->nullable();
            $table->string('eby', 50)->nullable();
            $table->timestamp('cdate')->nullable();
            $table->timestamp('edate')->nullable();
            $table->string('employee_status', 50)->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('ext_number', 20)->nullable();
            $table->string('ssn', 50)->nullable();
            $table->text('pic')->nullable();
            $table->text('sig')->nullable();
            $table->decimal('basicsalary', 15, 2)->nullable();
            $table->string('username', 50)->nullable();
            $table->string('pwd', 255)->nullable();
            $table->timestamp('llogin')->nullable();
            $table->integer('lcount')->nullable()->default(0);
            $table->boolean('is_first_time')->nullable()->default(true);
            $table->string('account_status', 50)->nullable();
            $table->string('sby', 50)->nullable();
            $table->timestamp('sdate')->nullable();
            $table->string('aby', 50)->nullable();
            $table->timestamp('adate')->nullable();
            $table->text('accesstoken')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('pid')->nullable();
            $table->string('pfno2', 50)->nullable();
            $table->string('tin', 50)->nullable();
            $table->string('current_qualification', 200)->nullable();
            $table->date('last_promotion_date')->nullable();
            $table->date('promotion_due_date')->nullable();
            $table->timestamp('last_token_date')->nullable();
            $table->unsignedBigInteger('health_insurance_id')->nullable();

            // Indexes for better performance
            $table->index('pfno');
            $table->index('username');
            $table->index('email');
            $table->index('mobile');
            $table->index('employee_status');
            $table->index('scheme_id');
            $table->index('emptype_id');
            $table->index('bank_id');
            $table->index('district_id');
            $table->index('person_id');
            $table->index('educational_level_id');
            
            // Foreign key constraints
            $table->foreign('educational_level_id')->references('id')->on('educational_levels')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employee');
    }
}
