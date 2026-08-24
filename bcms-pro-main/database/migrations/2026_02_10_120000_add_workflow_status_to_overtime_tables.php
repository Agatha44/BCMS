<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWorkflowStatusToOvertimeTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add workflow_status to overtime_requests
        Schema::connection('bcmis2')->table('overtime_requests', function (Blueprint $table) {
            $table->string('workflow_status', 50)
                ->nullable()
                ->after('status')
                ->comment('High-level workflow status (Applied, Validated, Reviewed, Approved, Paid, Rejected)');
        });

        // Add workflow_status to overtime_request_history
        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            $table->string('workflow_status', 50)
                ->nullable()
                ->after('status')
                ->comment('High-level workflow status after this action');
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
            if (Schema::connection('bcmis2')->hasColumn('overtime_requests', 'workflow_status')) {
                $table->dropColumn('workflow_status');
            }
        });

        Schema::connection('bcmis2')->table('overtime_request_history', function (Blueprint $table) {
            if (Schema::connection('bcmis2')->hasColumn('overtime_request_history', 'workflow_status')) {
                $table->dropColumn('workflow_status');
            }
        });
    }
}


