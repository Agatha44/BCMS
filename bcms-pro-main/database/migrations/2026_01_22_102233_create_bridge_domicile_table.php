<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeDomicileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_domicile', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Region/Domicile name');
            $table->text('description')->nullable()->comment('Description of the region');
            $table->boolean('is_active')->default(1)->comment('Whether the region is active');
            $table->integer('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->nullable()->comment('Created timestamp');
            $table->integer('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');
            
            $table->index('name');
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
        Schema::connection('bcmis2')->dropIfExists('bridge_domicile');
    }
}

