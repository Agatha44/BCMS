<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZreportProcessingJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zreport_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique()->comment('Unique job identifier');
            $table->string('status')->default('pending')->comment('Status: pending, running, completed, failed, cancelled');
            $table->string('start_date', 8)->nullable()->comment('Start date (YYYYMMDD)');
            $table->string('end_date', 8)->nullable()->comment('End date (YYYYMMDD)');
            $table->integer('batch_size')->default(1)->comment('Batch size used');
            $table->boolean('test_mode')->default(true)->comment('Test mode flag');
            $table->integer('total_missing')->default(0)->comment('Total missing dates found');
            $table->integer('total_processed')->default(0)->comment('Total dates processed so far');
            $table->integer('total_successful')->default(0)->comment('Total successful');
            $table->integer('total_failed')->default(0)->comment('Total failed');
            $table->integer('current_offset')->default(0)->comment('Current processing offset');
            $table->integer('remaining')->default(0)->comment('Remaining dates to process');
            $table->text('current_date_processing')->nullable()->comment('Current date being processed');
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->json('last_response')->nullable()->comment('Last API response data');
            $table->decimal('progress_percentage', 5, 2)->default(0)->comment('Progress percentage');
            $table->timestamp('started_at')->nullable()->comment('When processing started');
            $table->timestamp('completed_at')->nullable()->comment('When processing completed');
            $table->integer('estimated_seconds_remaining')->nullable()->comment('Estimated time remaining');
            $table->timestamps();
            
            // Indexes
            $table->index('job_id');
            $table->index('status');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zreport_processing_jobs');
    }
}

