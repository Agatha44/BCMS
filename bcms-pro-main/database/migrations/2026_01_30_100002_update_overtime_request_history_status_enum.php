<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateOvertimeRequestHistoryStatusEnum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update status enum to include new workflow statuses
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_request_history MODIFY COLUMN status ENUM('Pending', 'Validator Approved', 'Reviewer Approved', 'Rejected', 'In Batch', 'Submitted to Payment', 'Payment Approved', 'Payment Rejected', 'Payment Processing', 'Payment Completed', 'In Progress') DEFAULT 'Pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert to original enum values
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_request_history MODIFY COLUMN status ENUM('Pending', 'Supervisor Validated', 'Administrator Reviewed', 'Manager Reviewed', 'Approved', 'Rejected', 'In Progress') DEFAULT 'Pending'");
    }
}

