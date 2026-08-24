<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmployeeRoleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employee_role', function (Blueprint $table) {
            $table->id();
            $table->string('national_id', 50)->nullable()->comment('Reference to bridge_employee.national_id');
            $table->unsignedBigInteger('role_id')->nullable()->comment('Reference to bcmis2.roles.id');
            $table->boolean('is_active')->default(true)->comment('Indicates if the role assignment is active');
            $table->string('created_by', 50)->nullable()->comment('Created by user');
            $table->timestamp('created_at')->nullable()->comment('Created date');
            $table->string('modified_by', 50)->nullable()->comment('Modified by user');
            $table->timestamp('modified_at')->nullable()->comment('Modified date');
            $table->text('description')->nullable()->comment('Description of the role assignment');
            $table->timestamp('revoked_at')->nullable()->comment('Date when role was revoked');
            $table->string('revoked_by', 50)->nullable()->comment('User who revoked the role');

            // Indexes for better performance
            $table->index('national_id');
            $table->index('role_id');
            $table->index('is_active');
            $table->index(['national_id', 'role_id']);
            $table->index(['national_id', 'is_active']);
            
            // Note: No unique constraint to allow role assignment history
            // Application logic ensures only one active assignment exists per employee-role combination
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employee_role');
    }
}


