<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOvertimeRequestHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('overtime_request_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('overtime_request_id')->comment('Reference to overtime_requests table');
            $table->string('action', 50)->comment('Action performed (Submitted, Under Review, Approved, Rejected, Updated)');
            $table->enum('status', ['Pending', 'Supervisor Validated', 'Administrator Reviewed', 'Manager Reviewed', 'Approved', 'Rejected', 'In Progress'])->comment('Status after this action');
            $table->string('performed_by', 50)->comment('PF number of employee who performed the action (reference to bridge_employee.pfno)');
            $table->string('performed_by_role', 100)->nullable()->comment('Role name of the user at time of action');
            $table->text('comment')->nullable()->comment('Comment or note for this action');
            $table->timestamp('created_at')->useCurrent()->comment('Timestamp when action was performed');

            // Foreign key
            $table->foreign('overtime_request_id')
                ->references('id')
                ->on('overtime_requests')
                ->onDelete('cascade');

            // Indexes
            $table->index('overtime_request_id');
            $table->index('performed_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('overtime_request_history');
    }
}
