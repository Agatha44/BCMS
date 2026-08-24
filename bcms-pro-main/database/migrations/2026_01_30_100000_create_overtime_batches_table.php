<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique()->comment('Unique batch identifier');
            $table->string('batch_name')->nullable()->comment('Batch name/description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'processing', 'completed', 'failed'])->default('draft')->comment('Batch status');
            $table->decimal('total_amount', 12, 2)->default(0)->comment('Total amount for all overtime requests in batch');
            $table->integer('total_requests')->default(0)->comment('Total number of overtime requests in batch');
            $table->text('external_batch_id')->nullable()->comment('Batch ID from external payment system');
            $table->text('external_payment_request_id')->nullable()->comment('Payment request ID from external system');
            $table->string('payment_reference', 100)->nullable()->comment('Payment reference number from budget API');
            $table->json('payment_reference_response')->nullable()->comment('Full response from budget API for payment reference');
            $table->json('external_response')->nullable()->comment('Response from external system');
            $table->text('notes')->nullable()->comment('Batch notes/comments');
            $table->bigInteger('created_by')->nullable()->comment('User ID who created the batch');
            $table->bigInteger('updated_by')->nullable()->comment('User ID who last updated the batch');
            $table->timestamp('submitted_at')->nullable()->comment('When batch was submitted to external system');
            $table->timestamp('completed_at')->nullable()->comment('When batch processing was completed');
            $table->timestamps();

            // Indexes
            $table->index('batch_number');
            $table->index('status');
            $table->index('created_by');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_batches');
    }
}

