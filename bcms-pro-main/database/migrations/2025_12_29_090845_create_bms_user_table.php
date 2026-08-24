<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBmsUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bms_users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50)->comment('User first name');
            $table->string('middle_name', 50)->nullable()->comment('User middle name');
            $table->string('surname', 50)->comment('User surname');
            $table->string('nida_number', 50)->nullable()->comment('National ID number');
            $table->date('birth_date')->nullable()->comment('User birth date');
            $table->unsignedBigInteger('educational_level_id')->nullable()->comment('Reference to educational_levels table');
            $table->string('username', 50)->unique()->comment('Username for login');
            $table->string('email', 100)->nullable()->unique()->comment('User email address');
            $table->string('phone_number', 50)->nullable()->unique()->comment('User phone number');
            $table->unsignedBigInteger('role_id')->nullable()->comment('Reference to roles table');
            $table->string('finger_type', 100)->nullable()->comment('Type of fingerprint (e.g., left_thumb, right_index)');
            $table->text('fingerprint_template')->nullable()->comment('Fingerprint template data');
            $table->string('status', 50)->nullable()->comment('User status');
            $table->boolean('is_active')->default(1)->comment('Whether the user is active');
            $table->integer('created_by')->comment('User ID who created the record');
            $table->timestamp('created_at')->comment('Created timestamp');
            $table->integer('modified_by')->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->comment('Last modification timestamp');

            // Add indexes for better performance
            $table->index('role_id');
            $table->index('educational_level_id');
            $table->index('username');
            $table->index('email');
            $table->index('phone_number');
            $table->index('is_active');
            $table->index('finger_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bms_users');
    }
}
