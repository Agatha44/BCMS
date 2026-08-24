<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::connection('bcmis2')->statement(
            "ALTER TABLE overtime_submission_documents MODIFY COLUMN status ENUM('active', 'expired', 'inactive') NOT NULL DEFAULT 'active'"
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
            "ALTER TABLE overtime_submission_documents MODIFY COLUMN status ENUM('active', 'expired') NOT NULL DEFAULT 'active'"
        );
    }
};
