<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('notes');
            $table->unsignedBigInteger('category_id');
            $table->foreign('category_id')->references('id')->on('report_categories');
            $table->string('data_source');
            $table->json('data_fields')->nullable();
            $table->json('parameters')->nullable();
            $table->enum('status', [0, 1])->default(1);
            $table->boolean('is_public')->default(true)->nullable();
            $table->integer('created_by');

            $table->integer('updated_by')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->integer('last_run_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
}
