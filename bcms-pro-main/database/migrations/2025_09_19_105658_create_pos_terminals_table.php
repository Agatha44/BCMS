<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosTerminalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Terminal name/identifier');
            $table->bigInteger('lane_id')->nullable()->comment('Reference to lane table');
            $table->string('lane_number', 20)->comment('Lane number (e.g., A8, B12)');
            $table->string('mac_address', 17)->unique()->comment('Terminal MAC address');
            $table->string('ip_address', 45)->comment('Terminal IP address (IPv4/IPv6)');
            $table->string('terminal_type', 50)->default('POS')->comment('Type: POS, Kiosk, Mobile');
            $table->string('location', 255)->nullable()->comment('Physical location description');
            $table->string('status', 20)->default('active')->comment('Status: active, inactive, maintenance');
            $table->string('api_key', 100)->nullable()->comment('API key for terminal authentication');
            $table->datetime('last_heartbeat')->nullable()->comment('Last communication with server');
            $table->json('configuration')->nullable()->comment('Terminal-specific configuration (JSON)');
            $table->string('firmware_version', 50)->nullable()->comment('Terminal firmware version');
            $table->bigInteger('registered_by')->comment('User who registered this terminal');
            $table->timestamps();
            
            // Add indexes for better performance
            $table->index('lane_id');
            $table->index('lane_number');
            $table->index('mac_address');
            $table->index('ip_address');
            $table->index('status');
            $table->index('api_key');
            $table->index('last_heartbeat');
            $table->index('registered_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pos_terminals');
    }
}
