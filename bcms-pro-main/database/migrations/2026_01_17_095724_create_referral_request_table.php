<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReferralRequestTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('referral_request', function (Blueprint $table) {
            $table->id();
            $table->string('module_code', 50)->comment('Module identifier e.g. EMPLOYMENT_MANAGEMENT');
            $table->enum('action_type', ['CREATE', 'UPDATE', 'DELETE', 'TERMINATE'])->comment('Action type');
            $table->string('national_id', 50)->nullable()->comment('Employee National ID');
            $table->string('reference_id', 50)->nullable()->comment('Optional reference to employee record');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING')->comment('Request status');
            $table->unsignedBigInteger('initiated_by')->comment('User ID of maker');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('User ID of checker');
            $table->text('remarks')->nullable()->comment('Approval or rejection comments');
            $table->timestamp('created_at')->useCurrent()->comment('Request creation timestamp');
            $table->timestamp('actioned_at')->nullable()->comment('Approval/rejection timestamp');

            // Indexes for better performance
            $table->index('module_code');
            $table->index('action_type');
            $table->index('national_id');
            $table->index('status');
            $table->index('initiated_by');
            $table->index('approved_by');
            $table->index('created_at');
            $table->index(['module_code', 'status']);
            $table->index(['module_code', 'action_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('referral_request');
    }
}
