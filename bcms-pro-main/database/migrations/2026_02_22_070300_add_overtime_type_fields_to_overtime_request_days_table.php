<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOvertimeTypeFieldsToOvertimeRequestDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->table('overtime_request_days', function (Blueprint $table) {
            $table->enum('overtime_type', ['regular', 'holiday', 'special_task', 'weekend'])->default('regular')->after('day_date')->comment('Type of overtime: regular (standard 9-hour rule), holiday (all hours count), special_task (task-specific rule), weekend');
            $table->boolean('is_holiday')->default(false)->after('overtime_type')->comment('Whether this day is a public holiday');
            $table->unsignedBigInteger('special_task_id')->nullable()->after('is_holiday')->comment('Reference to special_tasks table if this is a special task overtime');
            $table->text('overtime_reason')->nullable()->after('special_task_id')->comment('Reason or context for overtime (e.g., holiday name, task name)');

            // Foreign key
            $table->foreign('special_task_id')
                ->references('id')
                ->on('special_tasks')
                ->onDelete('set null');

            // Index
            $table->index('overtime_type');
            $table->index('is_holiday');
            $table->index('special_task_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->table('overtime_request_days', function (Blueprint $table) {
            $table->dropForeign(['special_task_id']);
            $table->dropIndex(['overtime_type']);
            $table->dropIndex(['is_holiday']);
            $table->dropIndex(['special_task_id']);
            $table->dropColumn(['overtime_type', 'is_holiday', 'special_task_id', 'overtime_reason']);
        });
    }
}
