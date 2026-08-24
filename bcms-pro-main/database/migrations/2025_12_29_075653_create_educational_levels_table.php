<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEducationalLevelsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('educational_levels', function (Blueprint $table) {
            $table->id();
            $table->string('level_name', 100)->comment('Educational level Name (e.g., diploma, Degree)');
            $table->boolean('is_active')->default(1)->comment('Whether the education level is active');
            $table->integer('created_by')->comment('User ID who created the record');
            $table->timestamp('created_at')->comment('Created timestamp');
            $table->integer('modified_by')->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->comment('Last modification timestamp');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('educational_levels');
    }
}
