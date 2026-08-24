<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateZreportRepostLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zreport_repost_log', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->comment('Z-report date');
            $table->string('znumber', 20)->comment('Z-report number (YYYYMMDD format)');
            $table->time('report_time')->default('23:59:59')->comment('Z-report time');
            
            // VFD Registration Data
            $table->string('vfd_name', 200)->nullable()->comment('VFD registration name');
            $table->string('tin', 50)->nullable()->comment('Tax Identification Number');
            $table->string('vrn', 50)->nullable()->comment('VAT Registration Number');
            $table->string('reg_id', 50)->nullable()->comment('Registration ID');
            
            // Financial Data
            $table->bigInteger('daily_total')->default(0)->comment('Daily total amount');
            $table->bigInteger('cumulative_total')->default(0)->comment('Cumulative total (gross)');
            $table->decimal('net_amount', 15, 2)->default(0)->comment('Net amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('Tax amount');
            $table->bigInteger('cash_total')->default(0)->comment('Cash total');
            $table->bigInteger('emoney_total')->default(0)->comment('E-money total');
            
            // Transaction Data
            $table->integer('transaction_count')->default(0)->comment('Total number of transactions');
            
            // TRA API Response
            $table->integer('tra_status')->nullable()->comment('TRA API response status');
            $table->integer('tra_ackcode')->nullable()->comment('TRA acknowledgment code');
            $table->string('tra_ackmsg', 255)->nullable()->comment('TRA acknowledgment message');
            $table->date('tra_received_date')->nullable()->comment('TRA received date');
            $table->time('tra_received_time')->nullable()->comment('TRA received time');
            
            // Processing Information
            $table->boolean('test_mode')->default(false)->comment('Whether this was a test mode post');
            $table->text('tra_response_data')->nullable()->comment('Full TRA API response (JSON)');
            $table->text('error_message')->nullable()->comment('Error message if posting failed');
            $table->string('processing_status', 50)->default('success')->comment('Status: success, failed, pending');
            $table->decimal('processing_time_seconds', 8, 3)->nullable()->comment('Time taken to process');
            
            // Metadata
            $table->timestamp('posted_at')->nullable()->comment('When the Z-report was posted to TRA');
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('report_date');
            $table->index('znumber');
            $table->index('tra_ackcode');
            $table->index('processing_status');
            $table->index('test_mode');
            $table->index('posted_at');
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
        Schema::dropIfExists('zreport_repost_log');
    }
}

