<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('bcmis2')->create('employee_benefit', function (Blueprint $table) {
            $table->id('employee_benefit_id')->comment('Unique employee benefit record identifier');

            $table->string('employee_national_id', 50)->comment('Employee national ID (links to bridge_employee.national_id)');
            $table->unsignedBigInteger('benefit_type_id')->comment('Benefit type identifier (links to benefit_type.benefit_type_id)');

            $table->decimal('benefit_amount', 15, 2)->comment('Benefit amount applied to the employee for the period');
            $table->decimal('taxable_amount', 15, 2)->nullable()->comment('Taxable portion of the benefit amount');
            $table->decimal('tax_free_amount', 15, 2)->nullable()->comment('Non-taxable portion of the benefit amount');

            $table->date('effective_start_date')->nullable()->comment('Date when this benefit starts applying to the employee');
            $table->date('effective_end_date')->nullable()->comment('Date when this benefit stops applying to the employee');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this employee benefit record is active');

            $table->string('notes', 500)->nullable()->comment('Additional notes/remarks for this employee benefit');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            // Indexes for performance and data integrity
            $table->index('employee_national_id');
            $table->index('benefit_type_id');
            $table->index('is_active');
            $table->index('effective_start_date');
            $table->index('effective_end_date');
            $table->index('created_at');

            // Prevent duplicate benefit assignments per employee (for the same start date)
            $table->unique(['employee_national_id', 'benefit_type_id', 'effective_start_date'], 'uniq_emp_benefit_type_start_date');

            // Foreign keys
            $table->foreign('employee_national_id')
                ->references('national_id')
                ->on('bridge_employee')
                ->onDelete('cascade');

            $table->foreign('benefit_type_id')
                ->references('benefit_type_id')
                ->on('benefit_type')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('bcmis2')->dropIfExists('employee_benefit');
    }
};

