<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBridgeShiftDepartmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_shift_department');
        
        Schema::connection('bcmis2')->create('bridge_shift_department', function (Blueprint $table) {
            $table->id();
            $table->integer('shift_id');
            $table->bigInteger('department_id');
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();

            // Foreign key constraints
            $table->foreign('shift_id', 'bridge_shift_department_shift_id_foreign')
                ->references('id')
                ->on('bridge_shifts')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table->foreign('department_id', 'bridge_shift_department_department_id_foreign')
                ->references('department_id')
                ->on('departments')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            // Unique constraint to prevent duplicate shift-department assignments
            $table->unique(['shift_id', 'department_id'], 'bridge_shift_department_unique');

            // Indexes for better performance
            $table->index('shift_id');
            $table->index('department_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('bridge_shift_department');
    }
}
