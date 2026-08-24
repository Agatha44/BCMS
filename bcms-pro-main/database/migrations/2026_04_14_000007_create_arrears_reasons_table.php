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
        Schema::connection('bcmis2')->create('arrears_reasons', function (Blueprint $table) {
            $table->id('arrears_reason_id')->comment('Unique arrears reason identifier');

            $table->string('reason_name', 255)->comment('Full arrears reason name (e.g. Overtime, Salary Adjustment, Acting Allowance)');
            $table->string('reason_code', 50)->unique()->comment('Short unique code for the arrears reason');

            $table->boolean('is_taxable')
                ->default(true)
                ->comment('Whether amounts under this arrears reason are taxable');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this arrears reason is active');

            $table->date('start_date')->nullable()->comment('Start date for using this arrears reason');
            $table->date('end_date')->nullable()->comment('End date for using this arrears reason');

            $table->unsignedBigInteger('created_by')->nullable()->comment('User ID who created the record');
            $table->timestamp('created_at')->useCurrent()->comment('Creation timestamp');
            $table->unsignedBigInteger('modified_by')->nullable()->comment('User ID who last modified the record');
            $table->timestamp('modified_at')->nullable()->comment('Last modification timestamp');

            $table->index('reason_name');
            $table->index('is_taxable');
            $table->index('is_active');
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
        Schema::connection('bcmis2')->dropIfExists('arrears_reasons');
    }
};

