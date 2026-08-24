<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeSubmissionDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_submission_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50)->comment('Canonical key e.g. approval_memo');
            $table->string('document_name', 255)->nullable()->comment('Optional display label');
            $table->date('period_start')->comment('User-entered validity start');
            $table->date('period_end')->comment('User-entered validity end');
            $table->string('file_path', 500)->comment('Path relative to configured storage disk');
            $table->string('file_name', 255)->comment('Original upload filename');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->unsignedBigInteger('uploaded_by')->nullable()->comment('auth_user.id');
            $table->timestamp('uploaded_at')->useCurrent()->comment('Used for newest-wins resolution');
            $table->timestamps();

            $table->index(['document_type', 'status', 'period_start', 'period_end'], 'ot_sub_docs_type_status_period_idx');
            $table->index(['document_type', 'status', 'uploaded_at'], 'ot_sub_docs_type_status_uploaded_idx');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_submission_documents');
    }
}
