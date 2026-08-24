<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddReturnedStatusToOvertimeTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_requests MODIFY COLUMN status ENUM(
            'Pending', 'Validator Approved', 'Reviewer Approved', 'Returned',
            'Rejected', 'In Batch', 'Submitted to Payment', 'Payment Approved',
            'Payment Rejected', 'Payment Processing', 'Payment Completed'
        ) DEFAULT 'Pending'");

        DB::connection('bcmis2')->statement("ALTER TABLE overtime_request_history MODIFY COLUMN status ENUM(
            'Pending', 'Validator Approved', 'Reviewer Approved', 'Returned',
            'Rejected', 'In Batch', 'Submitted to Payment', 'Payment Approved',
            'Payment Rejected', 'Payment Processing', 'Payment Completed', 'In Progress'
        ) DEFAULT 'Pending'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('bcmis2')->statement("ALTER TABLE overtime_requests MODIFY COLUMN status ENUM(
            'Pending', 'Validator Approved', 'Reviewer Approved',
            'Rejected', 'In Batch', 'Submitted to Payment', 'Payment Approved',
            'Payment Rejected', 'Payment Processing', 'Payment Completed'
        ) DEFAULT 'Pending'");

        DB::connection('bcmis2')->statement("ALTER TABLE overtime_request_history MODIFY COLUMN status ENUM(
            'Pending', 'Validator Approved', 'Reviewer Approved', 'Rejected',
            'In Batch', 'Submitted to Payment', 'Payment Approved',
            'Payment Rejected', 'Payment Processing', 'Payment Completed', 'In Progress'
        ) DEFAULT 'Pending'");
    }
}
