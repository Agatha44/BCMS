<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveApplicationsTable extends Migration
{
    public function up()
    {
        Schema::connection('bcmis2')->create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->string('applicant_name')->nullable();
            $table->string('pf_number')->nullable();
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('days');
            $table->text('reason');
            $table->string('supportive_document_path')->nullable();
            $table->string('supportive_document_name')->nullable();
            $table->string('status', 32)->default('Applied')->index();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_comment')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_comment')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_at_stage', 32)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index('applicant_id');
            $table->index('pf_number');
        });
    }

    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('leave_applications');
    }
}
