<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Align employee_loan audit columns with bcmis.auth_user.pf_number (string), matching API usage.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('bcmis2')->statement(
            'ALTER TABLE employee_loan MODIFY created_by VARCHAR(50) NULL'
        );
        DB::connection('bcmis2')->statement(
            'ALTER TABLE employee_loan MODIFY modified_by VARCHAR(50) NULL'
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::connection('bcmis2')->statement(
            'ALTER TABLE employee_loan MODIFY created_by BIGINT UNSIGNED NULL'
        );
        DB::connection('bcmis2')->statement(
            'ALTER TABLE employee_loan MODIFY modified_by BIGINT UNSIGNED NULL'
        );
    }
};

