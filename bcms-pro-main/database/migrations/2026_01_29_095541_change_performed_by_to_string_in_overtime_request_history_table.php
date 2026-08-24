<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ChangePerformedByToStringInOvertimeRequestHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['performed_by']);
        });

        // Change column type from bigInteger to string
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_request_history MODIFY performed_by VARCHAR(50) NOT NULL COMMENT \'PF number of employee who performed the action (reference to bridge_employee.pfno)\'');

        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            // Recreate the index
            $table->index('performed_by');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            // Drop the index first
            $table->dropIndex(['performed_by']);
        });

        // Change column type back to bigInteger
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_request_history MODIFY performed_by BIGINT NOT NULL COMMENT \'User ID who performed the action (reference to auth_user table)\'');

        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            // Recreate the index
            $table->index('performed_by');
        });
    }
}
