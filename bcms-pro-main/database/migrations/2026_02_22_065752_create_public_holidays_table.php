<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePublicHolidaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('holiday_name', 255)->comment('Name of the public holiday');
            $table->date('holiday_date')->comment('Date of the public holiday');
            $table->string('holiday_type', 50)->nullable()->comment('Type of holiday (e.g., National, Regional, Religious)');
            $table->boolean('is_active')->default(true)->comment('Whether the holiday is active');
            $table->text('description')->nullable()->comment('Description or notes about the holiday');
            $table->string('created_by', 50)->nullable()->comment('User who created the record');
            $table->timestamps(6);
            $table->string('modified_by', 50)->nullable()->comment('User who last modified the record');
            $table->dateTime('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('holiday_date');
            $table->index('is_active');
            $table->unique('holiday_date', 'unique_holiday_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('public_holidays');
    }
}
