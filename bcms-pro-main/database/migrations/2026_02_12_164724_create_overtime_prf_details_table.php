<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimePrfDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_prf_details', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->comment('Reference to overtime_batches.batch_number');
            $table->string('payment_reference', 100)->nullable()->comment('Payment reference number from budget API');
            $table->json('prf_request_data')->nullable()->comment('PRF request data sent to budget API');
            $table->json('prf_response_data')->nullable()->comment('Full response from budget API');
            $table->enum('status', ['submitted', 'failed', 'prf_created'])->default('submitted')->comment('PRF submission status');
            $table->text('error_message')->nullable()->comment('Error message if submission failed');
            $table->bigInteger('submitted_by')->nullable()->comment('User ID who submitted the PRF');
            $table->timestamp('submitted_at')->nullable()->comment('When PRF was submitted');
            $table->timestamps();

            // Indexes
            $table->index('batch_number');
            $table->index('payment_reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_prf_details');
    }
}
