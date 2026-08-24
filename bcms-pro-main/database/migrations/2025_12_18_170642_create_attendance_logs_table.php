<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pf_number', 50)->nullable()->comment('Employee PF number received from biometric device');
            $table->string('ip_address', 45)->nullable()->comment('IP address of the biometric device or user');
            $table->timestamp('time_in')->comment('Time when user signed in via biometric device');
            $table->timestamp('time_out')->nullable()->comment('Time when user signed out via biometric device');
            $table->string('status', 50)->nullable()->comment('Session status (e.g., active, completed, cancelled)');
            $table->integer('session_duration')->nullable()->comment('Session duration in minutes (calculated from time_in and time_out)');
            $table->string('overtime_status', 20)->nullable()->comment('Overtime status: Overtime or Normal based on working hours');
            $table->boolean('is_late_arrival')->default(false)->comment('Indicates if employee had late arrival');
            $table->boolean('is_early_departure')->default(false)->comment('Indicates if employee had early departure');

            // Add indexes for better query performance
            $table->index('pf_number');
            $table->index('time_in');
            $table->index('time_out');
            $table->index('status');
            $table->index(['pf_number', 'time_in']); // Composite index for employee session queries
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('attendance_logs');
    }
}
