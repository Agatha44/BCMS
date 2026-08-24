<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeOfficeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_office', function (Blueprint $table) {
            $table->id();
            $table->string('office_name', 255)->comment('Office name (e.g., Nyerere Bridge, Main Scheme)');
            $table->string('office_code', 50)->unique()->nullable()->comment('Short code/identifier');
            $table->boolean('auto_generate_pf')->default(false)->comment('TRUE = auto-generate PF, FALSE = manual entry required');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('modified_by', 50)->nullable();
            $table->timestamp('modified_at')->nullable();

            // Indexes for better performance
            $table->index('office_code');
            $table->index('auto_generate_pf');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_office');
    }
}

