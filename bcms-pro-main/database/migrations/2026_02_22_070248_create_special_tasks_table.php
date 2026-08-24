<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSpecialTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('special_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_name', 255)->comment('Name or title of the special task');
            $table->text('description')->nullable()->comment('Description of the special task');
            $table->string('pf_number', 50)->comment('Employee PF number assigned to this task');
            $table->date('start_date')->comment('Start date of the task');
            $table->date('end_date')->comment('End date of the task');
            $table->enum('overtime_rule', ['all_hours', 'standard', 'custom'])->default('all_hours')->comment('Overtime calculation rule: all_hours (all hours count as overtime), standard (normal 9-hour rule), custom (custom calculation)');
            $table->decimal('custom_overtime_threshold', 8, 2)->nullable()->comment('Custom threshold in hours (for custom rule)');
            $table->boolean('is_pre_approved')->default(false)->comment('Whether overtime is pre-approved for this task');
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('active')->comment('Task status');
            $table->text('notes')->nullable()->comment('Additional notes or comments');
            $table->string('created_by', 50)->nullable()->comment('User who created the task');
            $table->timestamps(6);
            $table->string('modified_by', 50)->nullable()->comment('User who last modified the task');
            $table->dateTime('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes
            $table->index('pf_number');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('status');
            $table->index(['pf_number', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('special_tasks');
    }
}
