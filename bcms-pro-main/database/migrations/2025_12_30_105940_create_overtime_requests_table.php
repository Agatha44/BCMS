<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('employee_id')->comment('Reference to auth_user table - Employee who submitted the request');
            $table->date('month')->comment('Month for the overtime request (YYYY-MM-01 format)');
            $table->decimal('total_overtime_hours', 10, 2)->default(0)->comment('Total overtime hours for the month');
            $table->integer('total_days')->default(0)->comment('Total number of overtime days');
            $table->decimal('total_amount', 12, 2)->nullable()->default(0)->comment('Total amount calculated from overtime_request_days amounts');
            $table->enum('status', ['Pending', 'Supervisor Validated', 'Administrator Reviewed', 'Manager Reviewed', 'Approved', 'Rejected'])->default('Pending')->comment('Request status');
            $table->text('notes')->nullable()->comment('Admin notes or comments');
            $table->bigInteger('created_by')->nullable()->comment('User ID who created the request');
            $table->bigInteger('updated_by')->nullable()->comment('User ID who last updated the request');

            // Approval stage tracking fields
            $table->bigInteger('supervisor_validated_by')->nullable()->comment('User ID who validated as supervisor');
            $table->timestamp('supervisor_validated_at')->nullable();
            $table->text('supervisor_comment')->nullable()->comment('Supervisor validation comment/remark');

            $table->bigInteger('administrator_reviewed_by')->nullable()->comment('User ID who reviewed as administrator');
            $table->timestamp('administrator_reviewed_at')->nullable();
            $table->text('administrator_comment')->nullable()->comment('Administrator review comment/remark');

            $table->bigInteger('manager_reviewed_by')->nullable()->comment('User ID who reviewed as manager');
            $table->timestamp('manager_reviewed_at')->nullable();
            $table->text('manager_comment')->nullable()->comment('Manager review comment/remark');

            $table->bigInteger('dhra_approved_by')->nullable()->comment('User ID who approved as DHRA');
            $table->timestamp('dhra_approved_at')->nullable();
            $table->text('dhra_comment')->nullable()->comment('DHRA approval comment/remark');

            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('month');
            $table->index('status');
            $table->unique(['employee_id', 'month'], 'unique_employee_month');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_requests');
    }
}
