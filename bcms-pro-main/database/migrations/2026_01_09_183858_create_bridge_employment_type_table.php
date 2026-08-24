<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeEmploymentTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('bridge_employment_type', function (Blueprint $table) {
            $table->id('emptype_id');
            $table->string('emptype_name', 100);
            $table->integer('prob_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('emptype_name');
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_employment_type');
    }
}

