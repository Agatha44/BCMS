<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeDistrictTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_district', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('District name');
            $table->unsignedBigInteger('domicile_id')->comment('Foreign key to bridge_domicile (region) table');
            $table->text('description')->nullable()->comment('Description of the district');
            $table->boolean('is_active')->default(1)->comment('Whether the district is active');
            $table->integer('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->nullable()->comment('Created timestamp');
            $table->integer('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');
            
            $table->foreign('domicile_id')->references('id')->on('bridge_domicile')->onDelete('restrict');
            
            $table->index('name');
            $table->index('domicile_id');
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
        Schema::connection('bcmis2')->dropIfExists('bridge_district');
    }
}

