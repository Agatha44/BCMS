<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('departments', function (Blueprint $table) {
            $table->bigInteger('department_id', true)->comment('Primary key');
            $table->string('department_name', 255)->comment('Name of the department');
            $table->string('department_type', 100)->nullable()->comment('Type of department');
            $table->boolean('is_active')->default(true)->comment('Active status of the department');
            $table->string('created_by', 50)->nullable()->comment('User who created the record');
            $table->timestamps(6);
            $table->string('modified_by', 50)->nullable()->comment('User who last modified the record');
            $table->dateTime('modified_at')->nullable()->comment('Last modification timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('departments');
    }
}
