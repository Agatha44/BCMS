<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBatchFmsDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('batch_fms_details', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->comment('Reference to overtime_batches.batch_number');
            $table->string('payment_request_id', 100)->unique()->comment('Unique payment request ID (UUID) generated for FMS');
            $table->string('payment_reference', 100)->nullable()->comment('Payment reference from PRF/Budget API');
            $table->json('fms_request_data')->nullable()->comment('Full FMS request payload (paymentRequest, attachments, approvals)');
            $table->json('fms_response_data')->nullable()->comment('Full FMS response data');
            $table->json('fms_webhook_data')->nullable()->comment('Full FMS webhook callback data');
            $table->enum('fms_status', ['submitted', 'approved', 'processed', 'completed', 'paid', 'rejected', 'failed', 'cancelled'])->default('submitted')->comment('FMS payment status');
            $table->string('fms_payment_id', 100)->nullable()->comment('FMS payment ID returned from FMS system');
            $table->text('fms_status_message')->nullable()->comment('Status message from FMS');
            $table->text('fms_error_message')->nullable()->comment('Error message from FMS if submission failed');
            $table->bigInteger('submitted_by')->nullable()->comment('User ID who submitted to FMS');
            $table->timestamp('submitted_at')->nullable()->comment('When payment was submitted to FMS');
            $table->timestamp('processed_at')->nullable()->comment('When payment was processed by FMS');
            $table->timestamp('status_updated_at')->nullable()->comment('When FMS status was last updated');
            $table->timestamps();

            // Indexes
            $table->index('batch_number');
            $table->index('payment_request_id');
            $table->index('payment_reference');
            $table->index('fms_status');
            $table->index('fms_payment_id');
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
        Schema::connection('bcmis2')->dropIfExists('batch_fms_details');
    }
}
