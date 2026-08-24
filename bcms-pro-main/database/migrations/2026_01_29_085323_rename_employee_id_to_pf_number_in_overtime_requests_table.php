<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RenameEmployeeIdToPfNumberInOvertimeRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            // Drop the unique constraint first
            $table->dropUnique('unique_employee_month');
            // Drop the index
            $table->dropIndex(['employee_id']);
        });

        // Rename the column and change its type
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE employee_id pf_number VARCHAR(50) NULL COMMENT \'Reference to bridge_employee.pfno - Employee PF number who submitted the request\'');

        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            // Recreate the index
            $table->index('pf_number');
            // Recreate the unique constraint with new column name
            $table->unique(['pf_number', 'month'], 'unique_pf_number_month');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('unique_pf_number_month');
            // Drop the index
            $table->dropIndex(['pf_number']);
        });

        // Rename the column back and change its type
        DB::connection('bcmis2')->statement('ALTER TABLE overtime_requests CHANGE pf_number employee_id BIGINT UNSIGNED NULL COMMENT \'Reference to auth_user table - Employee who submitted the request\'');

        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            // Recreate the index
            $table->index('employee_id');
            // Recreate the unique constraint with original column name
            $table->unique(['employee_id', 'month'], 'unique_employee_month');
        });
    }
}
