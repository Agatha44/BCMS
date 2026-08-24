<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddBatchIdAndUpdateStatusInOvertimeRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            // Add batch_id column
            $table->unsignedBigInteger('batch_id')->nullable()->after('status')->comment('Reference to overtime_batches table');
            $table->index('batch_id');
            
            // Add external system status tracking fields
            $table->string('external_status', 50)->nullable()->after('batch_id')->comment('Status from external payment system');
            $table->text('external_payment_request_id')->nullable()->after('external_status')->comment('Payment request ID from external system');
            $table->json('external_response')->nullable()->after('external_payment_request_id')->comment('Response from external system');
            $table->timestamp('external_status_updated_at')->nullable()->after('external_response')->comment('When external status was last updated');
        });

        // Update status enum to include new workflow statuses
        // Note: We'll use ALTER TABLE to modify the enum
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_requests MODIFY COLUMN status ENUM('Pending', 'Validator Approved', 'Reviewer Approved', 'Rejected', 'In Batch', 'Submitted to Payment', 'Payment Approved', 'Payment Rejected', 'Payment Processing', 'Payment Completed') DEFAULT 'Pending'");
        
        // Rename approval fields to match new workflow using DB::statement
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE supervisor_validated_by validator_approved_by BIGINT NULL COMMENT \'User ID who approved as overtime validator\'');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE supervisor_validated_at validator_approved_at TIMESTAMP NULL');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE supervisor_comment validator_comment TEXT NULL COMMENT \'Validator approval comment/remark\'');
        
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE administrator_reviewed_by reviewer_approved_by BIGINT NULL COMMENT \'User ID who approved as overtime reviewer\'');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE administrator_reviewed_at reviewer_approved_at TIMESTAMP NULL');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE administrator_comment reviewer_comment TEXT NULL COMMENT \'Reviewer approval comment/remark\'');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'external_status', 'external_payment_request_id', 'external_response', 'external_status_updated_at']);
        });
        
        // Rename back using DB::statement
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE validator_approved_by supervisor_validated_by BIGINT NULL COMMENT \'User ID who validated as supervisor\'');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE validator_approved_at supervisor_validated_at TIMESTAMP NULL');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE validator_comment supervisor_comment TEXT NULL COMMENT \'Supervisor validation comment/remark\'');
        
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE reviewer_approved_by administrator_reviewed_by BIGINT NULL COMMENT \'User ID who reviewed as administrator\'');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE reviewer_approved_at administrator_reviewed_at TIMESTAMP NULL');
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE reviewer_comment administrator_comment TEXT NULL COMMENT \'Administrator review comment/remark\'');
        
        // Revert status enum
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_requests MODIFY COLUMN status ENUM('Pending', 'Supervisor Validated', 'Administrator Reviewed', 'Manager Reviewed', 'Approved', 'Rejected') DEFAULT 'Pending'");
    }
}

